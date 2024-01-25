// Note: the haveibeenpwned api requires strictly 5 chars
var hashlen = 5;

// This can be overridden in a following scripts
var sendSelector    = 'input[type=submit][name=submit][value=Register]';
var checkSelector   = 'input[type=submit][name=submit][value=Check]';
var pwdSelector     = 'input[name=password]';
var pwdHelpSelector = '#password-help';
var warnText        = 'This password has been seen {{count}} times in many site breaches. We strongly discourage you to use it.<br>Use a password manager for unique and strong passwords.';

function haveibeenpwned() {
    var endpoint = 'https://api.pwnedpasswords.com/range/';

    // We need a sha1 plugin for all this to work
    if (typeof sha1 !== 'function') {
        console.error('Missing sha-1 library dependency');
        return false;
    }

    var pwd_input = jQuery(pwdSelector)[0];

    // Skip if no password was provided
    if (!pwd_input.value) {
        return false;
    }

    // Calculate the hash
    var hash   = sha1(pwd_input.value).toUpperCase();
    var prefix = hash.substr(0, hashlen);
    var suffix = hash.substr(hashlen);
    var url    = endpoint + prefix;

    // Send a XHR request to the haveibeenpwned api
    jQuery.get(url, function (response) {
        // Format is a list of <full hash>:<count>
        var match = response.match(new RegExp(suffix+':(\\d+)'));
        // If we found the full hash in the response,
        // we warn the user
        if (match !== null) {
            warn(match[1]);
        }
    }).done(function() {
        // This is a non-blocking feature,
        // they can send once the check is done whatever the result is
        enable_send();
    })
}

function enable_send()
{
    var submit = jQuery(sendSelector)[0];
    var check  = jQuery(checkSelector)[0];

    submit.disabled = false;
    submit.classList.remove("disabled");

    check.classList.add("hidden");
}

function warn(count)
{
    var help = jQuery(pwdHelpSelector)[0];
    help.classList.remove("hidden");
    help.innerHTML = warnText.replace('{{count}}', parseInt(count));
}

// Event listener on the password input field
jQuery(document).ready(function () {
    jQuery(pwdSelector).on('textInput input', function() {
        // On change, we disable the send button
        // because we need a new check on the password
        jQuery(sendSelector)[0].disabled = true;
        jQuery(sendSelector)[0].classList.add("disabled");

        // Hide the previous warning if needed
        jQuery(pwdHelpSelector)[0].classList.add("hidden");

        // If the password input is empty,
        // we can add the check buttonwhat countries
        if (jQuery(pwdSelector)[0].value.length) {
            jQuery(checkSelector)[0].classList.remove("hidden");
        } else {
            jQuery(checkSelector)[0].classList.add("hidden");
        }
    });
});
