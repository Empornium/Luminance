<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Core\Service;

use Luminance\Errors\InternalError;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\SystemError;
use Luminance\Errors\AuthError;
use Luminance\Errors\InputError;

use \Defuse\Crypto\Crypto as DCrypto;
use \Defuse\Crypto\Exception as DCryptoEx;

class Crypto extends Service {

    protected $cryptoKey;
    protected $derivedKeys = [];

    public function __construct(Master $master) {
        parent::__construct($master);
        $this->cryptoKey = $this->hex2bin($this->master->settings->keys->crypto_key);
        if (!(strlen($this->cryptoKey) === 16)) {
            throw new ConfigurationError('No valid encryption key set!');
        }
    }

    public function encrypt($message, $keyIdentifier = 'default', $hex = false) {
        $key = $this->getDerivedKey($keyIdentifier);
        if (is_array($message)) $message = serialize($message);
        try {
            $ciphertext = DCrypto::encrypt($message, $key);
        } catch (DCryptoEx\CryptoTestFailedException $ex) {
            throw new SystemError('Cannot safely perform encryption');
        } catch (DCryptoEx\CannotPerformOperation $ex) {
            throw new SystemError('Cannot safely perform encryption');
        }
        if ($hex === true) $ciphertext = $this->bin2hex($ciphertext);
        return $ciphertext;
    }

    public function decrypt($ciphertext, $keyIdentifier = 'default', $hex = false) {
        # Always check for return value === false when using this!
        # It implies the ciphertext was invalid.
        if ($hex === true) {
            if (!ctype_xdigit($ciphertext)) {
                throw new InputError('Invalid token format');
            }
            $ciphertext = $this->hex2bin($ciphertext);
        }
        $key = $this->getDerivedKey($keyIdentifier);
        try {
            $message = DCrypto::decrypt($ciphertext, $key);
        } catch (DCryptoEx\InvalidCiphertextException $ex) {
            # This *could* be an attempt at sabotage. Unless the key was recently changed.
            return false;
        } catch (DCryptoEx\CryptoTestFailedException $ex) {
            throw new SystemError('Cannot safely perform encryption');
        } catch (DCryptoEx\CannotPerformOperation $ex) {
            throw new SystemError('Cannot safely perform encryption');
        }
        if (@unserialize($message)) $message = unserialize($message);
        return $message;
    }

    protected function shortHMAC($keyIdentifier, $data) {
        $key = $this->getDerivedKey($keyIdentifier);
        return substr(hash_hmac('sha256', $data, $key, true), 0, 12);
    }

    protected function shortHash($data) {
        return substr(hash('sha256', strval($data), true), 0, 4);
    }

    public function generateAuthToken($keyIdentifier, $cid, $action = '') {
        $timestamp = intval(date('U'));
        $baseToken = pack('a4a4N', $this->shortHash($action), $this->shortHash($cid), $timestamp);
        $fullToken = $baseToken . $this->shortHMAC($keyIdentifier, $baseToken);
        return self::bin2hex($fullToken);
    }

    public function checkAuthToken($keyIdentifier, $token, $cid, $action = '', $duration = 86400) {
        try {
            $fullToken = $this->hex2bin($token);
            $baseToken = substr($fullToken, 0, 12);
            if (strlen($baseToken) < 12) {
                throw new AuthError('Unauthorized', 'Malformed Auth Token', '/login');
            }
            $unpacked = unpack('a4actionHash/a4cid/Ntimestamp', $baseToken);
            if ($duration === 0) {
                $minTimestamp = $unpacked['timestamp'];
            } else {
                $minTimestamp = intval(date('U')) - $duration;
            }
            $result = (
                $this->shortHash($action) === $unpacked['actionHash'] &&
                $this->shortHash($cid) === $unpacked['cid'] &&
                $this->shortHMAC($keyIdentifier, $baseToken) === substr($fullToken, 12, 12) &&
                $unpacked['timestamp'] >= $minTimestamp
            );
            return $result;
        } catch (\Exception $e) {
            throw new AuthError('Unauthorized', 'Invalid authentication token', '/login');
        }
    }

    public function randomBytes($length) {
        $strong = null;
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if (!($strong === true)) {
            throw new SystemError("No strong PRNG available.");
        }
        return $bytes;
    }

    public function randomString($length = 32, $chars = 'abcdefghijklmnopqrstuvwxyz0123456789') {
        # This is intended to provide an unbiased random string suitable for use in strong crypto.
        # Don't mess with it unless you fully understand it.
        $mod = strlen($chars);
        $max = $mod * (int)(255 / $mod);
        $target = '';

        while (strlen($target) < $length) {
            $req = $length - strlen($target);
            $bytes = $this->randomBytes($req);
            for ($i = 0; $i < $req; $i++) {
                $val = ord(substr($bytes, $i, 1));
                if ($val < $max) {
                    $target .= substr($chars, $val % $mod, 1);
                }
            }
        }
        return $target;
    }

    protected function getDerivedKey($identifier) {
        if (array_key_exists($identifier, $this->derivedKeys)) {
            $derivedKey = $this->derivedKeys[$identifier];
        } else {
            # We prefix a short fixed random string to the actual identifier used for key derivation.
            # This might help make attacks against commonly used identifiers more difficult, or it might not help at all.
            # Either way, it can't do any harm.
            # Just don't change it and expect existing sessions etc. to still be valid!
            $fixedRandom = 'j0Q7';
            $derivedKey = $this->deriveKey($this->cryptoKey, 16, $fixedRandom . $identifier);
            $this->derivedKeys[$identifier] = $derivedKey;
        }
        if (!strlen($derivedKey)) {
            throw new InternalError("Derived key failure");
        }
        return $derivedKey;
    }

    protected static function deriveKey($ikm, $length, $info = '', $salt = null) {
        # This function has been borrowed from the php-encryption library ("HKDF" function).
        # Although the library recommends only using its public methods, there appear to be
        # no other general purpose key derivation functions available, and this is still better
        # than no key derivation at all.

        $hash = 'sha256';
        # Find the correct digest length as quickly as we can.
        $digestLength = 32;

        # Sanity-check the desired output length.
        if (empty($length) || !\is_int($length) ||
            $length < 0 || $length > 255 * $digestLength) {
            throw new InternalError(
                "Bad output length requested of deriveKey."
            );
        }

        # "if [salt] not provided, is set to a string of HashLen zeroes."
        if (\is_null($salt)) {
            $salt = \str_repeat("\x00", $digestLength);
        }

        # HKDF-Extract:
        # PRK = HMAC-Hash(salt, IKM)
        # The salt is the HMAC key.
        $prk = \hash_hmac($hash, $ikm, $salt, true);

        # HKDF-Expand:

        # This check is useless, but it serves as a reminder to the spec.
        if (self::ourStrlen($prk) < $digestLength) {
            throw new InternalError('Failed check in deriveKey');
        }

        # T(0) = ''
        $t = '';
        $lastBlock = '';
        for ($blockIndex = 1; self::ourStrlen($t) < $length; ++$blockIndex) {
            # T(i) = HMAC-Hash(PRK, T(i-1) | info | 0x??)
            $lastBlock = \hash_hmac(
                $hash,
                $lastBlock . $info . \chr($blockIndex),
                $prk,
                true
            );
            # T = T(1) | T(2) | T(3) | ... | T(N)
            $t .= $lastBlock;
        }

        # ORM = first L octets of T
        $orm = self::ourSubstr($t, 0, $length);
        if ($orm === false) {
            throw new InternalError('Failed check in deriveKey');
        }
        return $orm;
    }

    protected static function ourStrlen($str) {
        static $exists = null;
        if ($exists === null) {
            $exists = \function_exists('mb_strlen');
        }
        if (!empty($exists)) {
            $length = \mb_strlen($str, '8bit');
            if ($length === false) {
                throw new SystemError(
                    "mb_strlen() failed."
                );
            }
            return $length;
        } else {
            return \strlen($str);
        }
    }

    protected static function ourSubstr($str, $start, $length = null) {
        static $exists = null;
        if ($exists === null) {
            $exists = \function_exists('mb_substr');
        }
        if (!empty($exists)) {
            # mb_substr($str, 0, NULL, '8bit') returns an empty string on PHP
            # 5.3, so we have to find the length ourselves.
            if (!isset($length)) {
                if ($start >= 0) {
                    $length = self::ourStrlen($str) - $start;
                } else {
                    $length = -$start;
                }
            }

            return \mb_substr($str, $start, $length, '8bit');
        }

        # Unlike mb_substr(), substr() doesn't accept NULL for length
        if (isset($length)) {
            return \substr($str, $start, $length);
        } else {
            return \substr($str, $start);
        }
    }

    public static function bin2hex($binString) {
        return DCrypto::binToHex($binString);
    }

    public static function hex2bin($hexString) {
        try {
            return DCrypto::hexToBin($hexString);
        } catch (\RangeException $error) {
            throw new InputError('Invalid token format');
        }
    }
}
