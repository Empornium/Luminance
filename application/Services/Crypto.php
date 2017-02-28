<?php
namespace Luminance\Services;

use Luminance\Core\Master;
use Luminance\Errors\ConfigurationError;
use Luminance\Errors\SystemError;

use \Defuse\Crypto\Crypto as DCrypto;
use \Defuse\Crypto\Exception as DCryptoEx;

class Crypto extends Service {

    protected $CryptoKey;
    protected $DerivedKeys = [];

    public function __construct(Master $master) {
        parent::__construct($master);
        require_once($master->library_path . '/php-encryption/autoload.php');
        $this->CryptoKey = $this->hex2bin($this->master->settings->keys->crypto_key);
        if (strlen($this->CryptoKey) != 16) {
            throw new ConfigurationError('No valid encryption key set!');
        }
    }

    public function encrypt($Message, $KeyIdentifier = 'default') {
        $Key = $this->get_derived_key($KeyIdentifier);
        try {
            $Ciphertext = DCrypto::encrypt($Message, $Key);
        } catch (DCryptoEx\CryptoTestFailedException $ex) {
            throw new SystemError('Cannot safely perform encryption');
        } catch (DCryptoEx\CannotPerformOperationException $ex) {
            throw new SystemError('Cannot safely perform encryption');
        }
        return $Ciphertext;
    }

    public function decrypt($Ciphertext, $KeyIdentifier = 'default') {
        # Always check for return value === false when using this!
        # It implies the ciphertext was invalid.
        $Key = $this->get_derived_key($KeyIdentifier);
        try {
            $Message = DCrypto::decrypt($Ciphertext, $Key);
        } catch (DCryptoEx\InvalidCiphertextException $ex) {
            # This *could* be an attempt at sabotage. Unless the key was recently changed.
            return false;
        } catch (DCryptoEx\CryptoTestFailedException $ex) {
            throw new SystemError('Cannot safely perform encryption');
        } catch (DCryptoEx\CannotPerformOperationException $ex) {
            throw new SystemError('Cannot safely perform encryption');
        }
        return $Message;
    }

    protected function shortHMAC($keyIdentifier = 'HMAC', $data) {
        $key = $this->get_derived_key($keyIdentifier);
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
        $fullToken = $this->hex2bin($token);
        $baseToken = substr($fullToken, 0, 12);
        $unpacked = unpack('a4actionHash/a4cid/Ntimestamp', $baseToken);
        $minTimestamp = intval(date('U')) - $duration;
        $result = (
            $this->shortHash($action) === $unpacked['actionHash'] &&
            $this->shortHash($cid) === $unpacked['cid'] &&
            $this->shortHMAC($keyIdentifier, $baseToken) === substr($fullToken, 12, 12) &&
            $unpacked['timestamp'] >= $minTimestamp
        );
        return $result;
    }

    public function random_bytes($length) {
        $strong = null;
        $bytes = openssl_random_pseudo_bytes($length, $strong);
        if ($strong !== true) {
            throw new SystemError("No strong PRNG available.");
        }
        return $bytes;
    }

    public function random_string($length = 32, $chars = 'abcdefghijklmnopqrstuvwxyz0123456789') {
        # This is intended to provide an unbiased random string suitable for use in strong crypto.
        # Don't mess with it unless you fully understand it.
        $mod = strlen($chars);
        $max = $mod * (int)(255 / $mod);
        $target = '';

        while (strlen($target) < $length) {
            $req = $length - strlen($target);
            $bytes = $this->random_bytes($req);
            for ($i = 0; $i < $req; $i++) {
                $val = ord(substr($bytes, $i, 1));
                if ($val < $max) {
                    $target .= substr($chars, $val % $mod, 1);
                }
            }
        }
        return $target;
    }

    protected function get_derived_key($Identifier) {
        if (array_key_exists($Identifier, $this->DerivedKeys)) {
            $DerivedKey = $this->DerivedKeys[$Identifier];
        } else {
            # We prefix a short fixed random string to the actual identifier used for key derivation.
            # This might help make attacks against commonly used identifiers more difficult, or it might not help at all.
            # Either way, it can't do any harm.
            # Just don't change it and expect existing sessions etc. to still be valid!
            $FixedRandom = 'j0Q7';
            $DerivedKey = $this->derive_key($this->CryptoKey, 16, $FixedRandom . $Identifier);
            $this->DerivedKeys[$Identifier] = $DerivedKey;
        }
        if (!strlen($DerivedKey)) {
            throw new InternalException("Derived key failure");
        }
        return $DerivedKey;
    }

    protected static function derive_key($ikm, $length, $info = '', $salt = null) {
        # This function has been borrowed from the php-encryption library ("HKDF" function).
        # Although the library recommends only using its public methods, there appear to be
        # no other general purpose key derivation functions available, and this is still better
        # than no key derivation at all.

        $hash = 'sha256';
        // Find the correct digest length as quickly as we can.
        $digest_length = 32;

        // Sanity-check the desired output length.
        if (empty($length) || !\is_int($length) ||
            $length < 0 || $length > 255 * $digest_length) {
            throw new InternalError(
                "Bad output length requested of derive_key."
            );
        }

        // "if [salt] not provided, is set to a string of HashLen zeroes."
        if (\is_null($salt)) {
            $salt = \str_repeat("\x00", $digest_length);
        }

        // HKDF-Extract:
        // PRK = HMAC-Hash(salt, IKM)
        // The salt is the HMAC key.
        $prk = \hash_hmac($hash, $ikm, $salt, true);

        // HKDF-Expand:

        // This check is useless, but it serves as a reminder to the spec.
        if (self::our_strlen($prk) < $digest_length) {
            throw new InternalError('Failed check in derive_key');
        }

        // T(0) = ''
        $t = '';
        $last_block = '';
        for ($block_index = 1; self::our_strlen($t) < $length; ++$block_index) {
            // T(i) = HMAC-Hash(PRK, T(i-1) | info | 0x??)
            $last_block = \hash_hmac(
                $hash,
                $last_block . $info . \chr($block_index),
                $prk,
                true
            );
            // T = T(1) | T(2) | T(3) | ... | T(N)
            $t .= $last_block;
        }

        // ORM = first L octets of T
        $orm = self::our_substr($t, 0, $length);
        if ($orm === FALSE) {
            throw new InternalError('Failed check in derive_key');
        }
        return $orm;
    }

    protected static function our_strlen($str) {
        static $exists = null;
        if ($exists === null) {
            $exists = \function_exists('mb_strlen');
        }
        if ($exists) {
            $length = \mb_strlen($str, '8bit');
            if ($length === FALSE) {
                throw new SystemError(
                    "mb_strlen() failed."
                );
            }
            return $length;
        } else {
            return \strlen($str);
        }
    }

    protected static function our_substr($str, $start, $length = null) {
        static $exists = null;
        if ($exists === null) {
            $exists = \function_exists('mb_substr');
        }
        if ($exists)
        {
            // mb_substr($str, 0, NULL, '8bit') returns an empty string on PHP
            // 5.3, so we have to find the length ourselves.
            if (!isset($length)) {
                if ($start >= 0) {
                    $length = self::our_strlen($str) - $start;
                } else {
                    $length = -$start;
                }
            }

            return \mb_substr($str, $start, $length, '8bit');
        }

        // Unlike mb_substr(), substr() doesn't accept NULL for length
        if (isset($length)) {
            return \substr($str, $start, $length);
        } else {
            return \substr($str, $start);
        }
    }

    public static function bin2hex($BinString) {
        return DCrypto::binToHex($BinString);
    }

    public static function hex2bin($HexString) {
        return DCrypto::hexToBin($HexString);
    }

}
