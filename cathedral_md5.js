/*
 * A JavaScript implementation of the RSA Data Security, Inc. MD5 Message
 * Digest Algorithm, as defined in RFC 1321.
 * Version 2.2 Copyright (C) Paul Johnston 1999 - 2009
 * Other contributors: Greg Holt, Andrew Kepert, Ydnar, Lostinet
 * Distributed under the BSD License
 * See http://pajhome.org.uk/crypt/md5 for more info.
 */

/*
 * Key populated on page.
 */
var pskey = null;

/*
 * Configurable variables. You may need to tweak these to be compatible with
 * the server-side, but the defaults work in most cases.
 */
var hexcase = 0;  /* hex output format. 0 - lowercase; 1 - uppercase        */
var b64pad  = ""; /* base-64 pad character. "=" for strict RFC compliance   */

/*
 * These are the functions you'll usually want to call
 * They take string arguments and return either hex or base-64 encoded strings
 */
function hex_md5(s)    { return rstr2hex(rstr_md5(str2rstr_utf8(s))); }
function b64_md5(s)    { return rstr2b64(rstr_md5(str2rstr_utf8(s))); }
function any_md5(s, e) { return rstr2any(rstr_md5(str2rstr_utf8(s)), e); }
function hex_hmac_md5(k, d)
{ return rstr2hex(rstr_hmac_md5(str2rstr_utf8(k), str2rstr_utf8(d))); }
function b64_hmac_md5(k, d)
{ return rstr2b64(rstr_hmac_md5(str2rstr_utf8(k), str2rstr_utf8(d))); }
function any_hmac_md5(k, d, e)
{ return rstr2any(rstr_hmac_md5(str2rstr_utf8(k), str2rstr_utf8(d)), e); }

/*
 * Perform a simple self-test to see if the VM is working
 */
function md5_vm_test()
{
    return hex_md5("abc").toLowerCase() == "900150983cd24fb0d6963f7d28e17f72";
}

/*
 * Calculate the MD5 of a raw string
 */
function rstr_md5(s)
{
    return binl2rstr(binl_md5(rstr2binl(s), s.length * 8));
}

/*
 * Calculate the HMAC-MD5, of a key and some data (raw strings)
 */
function rstr_hmac_md5(key, data)
{
    var bkey = rstr2binl(key);
    if(bkey.length > 16) bkey = binl_md5(bkey, key.length * 8);

    var ipad = Array(16), opad = Array(16);
    for(var i = 0; i < 16; i++)
    {
        ipad[i] = bkey[i] ^ 0x36363636;
        opad[i] = bkey[i] ^ 0x5C5C5C5C;
    }

    var hash = binl_md5(ipad.concat(rstr2binl(data)), 512 + data.length * 8);
    return binl2rstr(binl_md5(opad.concat(hash), 512 + 128));
}

/*
 * Convert a raw string to a hex string
 */
function rstr2hex(input)
{
    try { hexcase } catch(e) { hexcase=0; }
    var hex_tab = hexcase ? "0123456789ABCDEF" : "0123456789abcdef";
    var output = "";
    var x;
    for(var i = 0; i < input.length; i++)
    {
        x = input.charCodeAt(i);
        output += hex_tab.charAt((x >>> 4) & 0x0F)
            +  hex_tab.charAt( x        & 0x0F);
    }
    return output;
}

/*
 * Convert a raw string to a base-64 string
 */
function rstr2b64(input)
{
    try { b64pad } catch(e) { b64pad=''; }
    var tab = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/";
    var output = "";
    var len = input.length;
    for(var i = 0; i < len; i += 3)
    {
        var triplet = (input.charCodeAt(i) << 16)
            | (i + 1 < len ? input.charCodeAt(i+1) << 8 : 0)
            | (i + 2 < len ? input.charCodeAt(i+2)      : 0);
        for(var j = 0; j < 4; j++)
        {
            if(i * 8 + j * 6 > input.length * 8) output += b64pad;
            else output += tab.charAt((triplet >>> 6*(3-j)) & 0x3F);
        }
    }
    return output;
}

/*
 * Convert a raw string to an arbitrary string encoding
 */
function rstr2any(input, encoding)
{
    var divisor = encoding.length;
    var i, j, q, x, quotient;

    /* Convert to an array of 16-bit big-endian values, forming the dividend */
    var dividend = Array(Math.ceil(input.length / 2));
    for(i = 0; i < dividend.length; i++)
    {
        dividend[i] = (input.charCodeAt(i * 2) << 8) | input.charCodeAt(i * 2 + 1);
    }

    /*
     * Repeatedly perform a long division. The binary array forms the dividend,
     * the length of the encoding is the divisor. Once computed, the quotient
     * forms the dividend for the next step. All remainders are stored for later
     * use.
     */
    var full_length = Math.ceil(input.length * 8 /
        (Math.log(encoding.length) / Math.log(2)));
    var remainders = Array(full_length);
    for(j = 0; j < full_length; j++)
    {
        quotient = Array();
        x = 0;
        for(i = 0; i < dividend.length; i++)
        {
            x = (x << 16) + dividend[i];
            q = Math.floor(x / divisor);
            x -= q * divisor;
            if(quotient.length > 0 || q > 0)
                quotient[quotient.length] = q;
        }
        remainders[j] = x;
        dividend = quotient;
    }

    /* Convert the remainders to the output string */
    var output = "";
    for(i = remainders.length - 1; i >= 0; i--)
        output += encoding.charAt(remainders[i]);

    return output;
}

/*
 * Encode a string as utf-8.
 * For efficiency, this assumes the input is valid utf-16.
 */
function str2rstr_utf8(input)
{
    var output = "";
    var i = -1;
    var x, y;

    while(++i < input.length)
    {
        /* Decode utf-16 surrogate pairs */
        x = input.charCodeAt(i);
        y = i + 1 < input.length ? input.charCodeAt(i + 1) : 0;
        if(0xD800 <= x && x <= 0xDBFF && 0xDC00 <= y && y <= 0xDFFF)
        {
            x = 0x10000 + ((x & 0x03FF) << 10) + (y & 0x03FF);
            i++;
        }

        /* Encode output as utf-8 */
        if(x <= 0x7F)
            output += String.fromCharCode(x);
        else if(x <= 0x7FF)
            output += String.fromCharCode(0xC0 | ((x >>> 6 ) & 0x1F),
                0x80 | ( x         & 0x3F));
        else if(x <= 0xFFFF)
            output += String.fromCharCode(0xE0 | ((x >>> 12) & 0x0F),
                0x80 | ((x >>> 6 ) & 0x3F),
                0x80 | ( x         & 0x3F));
        else if(x <= 0x1FFFFF)
            output += String.fromCharCode(0xF0 | ((x >>> 18) & 0x07),
                0x80 | ((x >>> 12) & 0x3F),
                0x80 | ((x >>> 6 ) & 0x3F),
                0x80 | ( x         & 0x3F));
    }
    return output;
}

/*
 * Encode a string as utf-16
 */
function str2rstr_utf16le(input)
{
    var output = "";
    for(var i = 0; i < input.length; i++)
        output += String.fromCharCode( input.charCodeAt(i)        & 0xFF,
            (input.charCodeAt(i) >>> 8) & 0xFF);
    return output;
}

function str2rstr_utf16be(input)
{
    var output = "";
    for(var i = 0; i < input.length; i++)
        output += String.fromCharCode((input.charCodeAt(i) >>> 8) & 0xFF,
            input.charCodeAt(i)        & 0xFF);
    return output;
}

/*
 * Convert a raw string to an array of little-endian words
 * Characters >255 have their high-byte silently ignored.
 */
function rstr2binl(input)
{
    var output = Array(input.length >> 2);
    for(var i = 0; i < output.length; i++)
        output[i] = 0;
    for(var i = 0; i < input.length * 8; i += 8)
        output[i>>5] |= (input.charCodeAt(i / 8) & 0xFF) << (i%32);
    return output;
}

/*
 * Convert an array of little-endian words to a string
 */
function binl2rstr(input)
{
    var output = "";
    for(var i = 0; i < input.length * 32; i += 8)
        output += String.fromCharCode((input[i>>5] >>> (i % 32)) & 0xFF);
    return output;
}

/*
 * Calculate the MD5 of an array of little-endian words, and a bit length.
 */
function binl_md5(x, len)
{
    /* append padding */
    x[len >> 5] |= 0x80 << ((len) % 32);
    x[(((len + 64) >>> 9) << 4) + 14] = len;

    var a =  1732584193;
    var b = -271733879;
    var c = -1732584194;
    var d =  271733878;

    for(var i = 0; i < x.length; i += 16)
    {
        var olda = a;
        var oldb = b;
        var oldc = c;
        var oldd = d;

        a = md5_ff(a, b, c, d, x[i+ 0], 7 , -680876936);
        d = md5_ff(d, a, b, c, x[i+ 1], 12, -389564586);
        c = md5_ff(c, d, a, b, x[i+ 2], 17,  606105819);
        b = md5_ff(b, c, d, a, x[i+ 3], 22, -1044525330);
        a = md5_ff(a, b, c, d, x[i+ 4], 7 , -176418897);
        d = md5_ff(d, a, b, c, x[i+ 5], 12,  1200080426);
        c = md5_ff(c, d, a, b, x[i+ 6], 17, -1473231341);
        b = md5_ff(b, c, d, a, x[i+ 7], 22, -45705983);
        a = md5_ff(a, b, c, d, x[i+ 8], 7 ,  1770035416);
        d = md5_ff(d, a, b, c, x[i+ 9], 12, -1958414417);
        c = md5_ff(c, d, a, b, x[i+10], 17, -42063);
        b = md5_ff(b, c, d, a, x[i+11], 22, -1990404162);
        a = md5_ff(a, b, c, d, x[i+12], 7 ,  1804603682);
        d = md5_ff(d, a, b, c, x[i+13], 12, -40341101);
        c = md5_ff(c, d, a, b, x[i+14], 17, -1502002290);
        b = md5_ff(b, c, d, a, x[i+15], 22,  1236535329);

        a = md5_gg(a, b, c, d, x[i+ 1], 5 , -165796510);
        d = md5_gg(d, a, b, c, x[i+ 6], 9 , -1069501632);
        c = md5_gg(c, d, a, b, x[i+11], 14,  643717713);
        b = md5_gg(b, c, d, a, x[i+ 0], 20, -373897302);
        a = md5_gg(a, b, c, d, x[i+ 5], 5 , -701558691);
        d = md5_gg(d, a, b, c, x[i+10], 9 ,  38016083);
        c = md5_gg(c, d, a, b, x[i+15], 14, -660478335);
        b = md5_gg(b, c, d, a, x[i+ 4], 20, -405537848);
        a = md5_gg(a, b, c, d, x[i+ 9], 5 ,  568446438);
        d = md5_gg(d, a, b, c, x[i+14], 9 , -1019803690);
        c = md5_gg(c, d, a, b, x[i+ 3], 14, -187363961);
        b = md5_gg(b, c, d, a, x[i+ 8], 20,  1163531501);
        a = md5_gg(a, b, c, d, x[i+13], 5 , -1444681467);
        d = md5_gg(d, a, b, c, x[i+ 2], 9 , -51403784);
        c = md5_gg(c, d, a, b, x[i+ 7], 14,  1735328473);
        b = md5_gg(b, c, d, a, x[i+12], 20, -1926607734);

        a = md5_hh(a, b, c, d, x[i+ 5], 4 , -378558);
        d = md5_hh(d, a, b, c, x[i+ 8], 11, -2022574463);
        c = md5_hh(c, d, a, b, x[i+11], 16,  1839030562);
        b = md5_hh(b, c, d, a, x[i+14], 23, -35309556);
        a = md5_hh(a, b, c, d, x[i+ 1], 4 , -1530992060);
        d = md5_hh(d, a, b, c, x[i+ 4], 11,  1272893353);
        c = md5_hh(c, d, a, b, x[i+ 7], 16, -155497632);
        b = md5_hh(b, c, d, a, x[i+10], 23, -1094730640);
        a = md5_hh(a, b, c, d, x[i+13], 4 ,  681279174);
        d = md5_hh(d, a, b, c, x[i+ 0], 11, -358537222);
        c = md5_hh(c, d, a, b, x[i+ 3], 16, -722521979);
        b = md5_hh(b, c, d, a, x[i+ 6], 23,  76029189);
        a = md5_hh(a, b, c, d, x[i+ 9], 4 , -640364487);
        d = md5_hh(d, a, b, c, x[i+12], 11, -421815835);
        c = md5_hh(c, d, a, b, x[i+15], 16,  530742520);
        b = md5_hh(b, c, d, a, x[i+ 2], 23, -995338651);

        a = md5_ii(a, b, c, d, x[i+ 0], 6 , -198630844);
        d = md5_ii(d, a, b, c, x[i+ 7], 10,  1126891415);
        c = md5_ii(c, d, a, b, x[i+14], 15, -1416354905);
        b = md5_ii(b, c, d, a, x[i+ 5], 21, -57434055);
        a = md5_ii(a, b, c, d, x[i+12], 6 ,  1700485571);
        d = md5_ii(d, a, b, c, x[i+ 3], 10, -1894986606);
        c = md5_ii(c, d, a, b, x[i+10], 15, -1051523);
        b = md5_ii(b, c, d, a, x[i+ 1], 21, -2054922799);
        a = md5_ii(a, b, c, d, x[i+ 8], 6 ,  1873313359);
        d = md5_ii(d, a, b, c, x[i+15], 10, -30611744);
        c = md5_ii(c, d, a, b, x[i+ 6], 15, -1560198380);
        b = md5_ii(b, c, d, a, x[i+13], 21,  1309151649);
        a = md5_ii(a, b, c, d, x[i+ 4], 6 , -145523070);
        d = md5_ii(d, a, b, c, x[i+11], 10, -1120210379);
        c = md5_ii(c, d, a, b, x[i+ 2], 15,  718787259);
        b = md5_ii(b, c, d, a, x[i+ 9], 21, -343485551);

        a = safe_add(a, olda);
        b = safe_add(b, oldb);
        c = safe_add(c, oldc);
        d = safe_add(d, oldd);
    }
    return Array(a, b, c, d);
}

/*
 * These functions implement the four basic operations the algorithm uses.
 */
function md5_cmn(q, a, b, x, s, t)
{
    return safe_add(bit_rol(safe_add(safe_add(a, q), safe_add(x, t)), s),b);
}
function md5_ff(a, b, c, d, x, s, t)
{
    return md5_cmn((b & c) | ((~b) & d), a, b, x, s, t);
}
function md5_gg(a, b, c, d, x, s, t)
{
    return md5_cmn((b & d) | (c & (~d)), a, b, x, s, t);
}
function md5_hh(a, b, c, d, x, s, t)
{
    return md5_cmn(b ^ c ^ d, a, b, x, s, t);
}
function md5_ii(a, b, c, d, x, s, t)
{
    return md5_cmn(c ^ (b | (~d)), a, b, x, s, t);
}

/*
 * Add integers, wrapping at 2^32. This uses 16-bit operations internally
 * to work around bugs in some JS interpreters.
 */
function safe_add(x, y)
{
    var lsw = (x & 0xFFFF) + (y & 0xFFFF);
    var msw = (x >> 16) + (y >> 16) + (lsw >> 16);
    return (msw << 16) | (lsw & 0xFFFF);
}

/*
 * Bitwise rotate a 32-bit number to the left.
 */
function bit_rol(num, cnt)
{
    return (num << cnt) | (num >>> (32 - cnt));
}

function doAdminLogin(form) {
    //deleteCookie("psaid");
    var pw = form.password.value;
    var i = pw.indexOf(";");
    var unHidden  = form.username.type == "hidden";
    if ( !unHidden ) {
        //Handle un/pw input version
        pw2 = pw;
        pw = b64_md5(pw);					// Added in move to pcas
        form.password.value = hex_hmac_md5(pskey, pw);
        if (form.ldappassword != null) {
            // LDAP is enabled, so send the clear-text password
            // Customers should have SSL enabled if they are using LDAP
            form.ldappassword.value = pw2; // Send the pw for LDAP
        }

    } else {
        // Handle legacy single input version
        form.username.value = pw.substring(0, i);
        pw = pw.substring(i + 1); // Get the password
        pw2 = pw;
        pw = b64_md5(pw);					// Added in move to pcas
        form.password.value = hex_hmac_md5(pskey, pw);
        if (form.ldappassword != null) {
            // LDAP is enabled, so send the clear-text password
            // Customers should have SSL enabled if they are using LDAP
            form.ldappassword.value = pw2; // Send the pw for LDAP
        }
    }
    return true;
}


function doTeacherLogin(form) {
    var pw = form.password.value;
    var pw2 = pw; 							// Keep a raw version for ldap
    //pw = pw.toLowerCase();			eliminated with move to pcas
    pw = b64_md5(pw);					// Added in move to pcas
    form.password.value = hex_hmac_md5(pskey, pw);
    if (form.ldappassword!=null) {
        // LDAP is enabled, so send the clear-text password
        // Customers should have SSL enabled if they are using LDAP
        form.ldappassword.value = pw2; // Use raw version of the pw
    }

    // Translator Login
    var translatorpw = form.translatorpw.value;
    var i = translatorpw.indexOf(";");
    if (i < 0) {
        form.translator_username.value = translatorpw;
        form.translator_password.value = "";
    }
    else {
        form.translator_username.value = translatorpw.substring(0,i);
        translatorpw = translatorpw.substring(i+1); // Get the password
        translatorpw2 = translatorpw;
        translatorpw = b64_md5(translatorpw);					// Added in move to pcas
        form.translator_password.value = hex_hmac_md5(pskey, translatorpw);
        if (form.translator_ldappassword!=null) {
            // LDAP is enabled, so send the clear-text password
            // Customers should have SSL enabled if they are using LDAP
            form.translator_ldappassword.value = translatorpw2; // Send the pw for LDAP
        }
    }

    return true;
}

function doPCASLogin(form)
{
    var originalpw = form.pw.value;
    var b64pw = b64_md5(originalpw);
    var hmac_md5pw = hex_hmac_md5(pskey, b64pw)
    form.pw.value = hmac_md5pw;
    form.dbpw.value = hex_hmac_md5(pskey, originalpw.toLowerCase())
    if (form.ldappassword!=null) {
        // LDAP is enabled, so send the clear-text password
        // Customers should have SSL enabled if they are using LDAP
        form.ldappassword.value = originalpw; // Send the unmangled password
    }

    // Translator Login
    var translatorpw = form.translatorpw.value;
    var i = translatorpw.indexOf(";");
    if (i < 0) {
        form.translator_username.value = translatorpw;
        form.translator_password.value = "";
    }
    else {
        form.translator_username.value = translatorpw.substring(0,i);
        translatorpw = translatorpw.substring(i+1); // Get the password
        translatorpw2 = translatorpw;
        translatorpw = b64_md5(translatorpw);					// Added in move to pcas
        form.translator_password.value = hex_hmac_md5(pskey, translatorpw);
        if (form.translator_ldappassword!=null) {
            // LDAP is enabled, so send the clear-text password
            // Customers should have SSL enabled if they are using LDAP
            form.translator_ldappassword.value = translatorpw2; // Send the pw for LDAP
        }
    }

    return true;
}


function encryptMultipleStudentsAccesPasswords(form)
{
    form.pstoken.value=pstoken;

    // Encrypt guardian password
    encryptGuardianPassword(form);

    // Encrypt student access password
    var i = 0;
    var pw = document.getElementById("studentInfo[" + i + "].accessPassword");
    while(pw != undefined){
        pw = document.getElementById("studentInfo[" + i + "].accessPassword");
        // Added check for existing pw element because it was causing this script to crash in the create_multi_student_account.html page.
        if (pw) {
            if(pw.value != undefined && trim(pw.value) != ""){
                //Store the unencrypted password to a temporary value for debugging
                var unEncryptedPassword = pw.value;
                var pw2 = b64_md5(pw.value);
                pw.value = hex_hmac_md5(pskey, pw2);
                //alert("Student Numner: " + i + " unEncrypted PW: " + unEncryptedPassword + " Encrypted Password: " + pw.value);

            }
        }

        i++;
    }
    return true;
}



function encryptSingleStudentAccesPassword(form)
{
    //alert("encryptSingleStudentAccesPassword");
    var pw = document.getElementById("studentInfoToAdd.accessPassword");
    //alert("pstoken: " + form.pstoken.value);
    // alert("pskey: " + form.pskey.value);
    if(pw != undefined && trim(pw.value) != ""){
        var unEncryptedPassword = pw.value;
        var pw2 = b64_md5(pw.value);
        pw.value= hex_hmac_md5(form.pskey.value, pw2);
        form.pskey.value="";
        //alert("unEncrypted PW: " + unEncryptedPassword+ " Encrypted Password: " + pw.value);
    }
    return true;
}

function encryptGuardianPassword(form) {

    //This function has been refactored to use parameters for the element Id's so that it can be reused.
    return encryptGuardianPasswords(form, "myForm_accountInfo_password", "editedPassword", "passwordConfirm");

}




function encryptGuardianPasswords(form, origPasswordId, newPasswordId, newConfirmPasswordId) {

    var myPsKey;
    var formHasKey = false;
    if (pskey == undefined || pskey == null) {
        if (form == undefined || form.pskey == undefined || form.pskey.value == undefined) {
            return false;
        } else {
            myPsKey = form.pskey.value;
            formHasKey = true;
        }

    }
    else {
        myPsKey = pskey;
    }

    if(myPsKey.length < 32) {
        return false;
    }


    // Encrypt guardian password
    var pskeyArray = hexToByteArray(myPsKey.substring(0, 32));
    var gpw1 = document.getElementById(origPasswordId);
    if(gpw1 != undefined) {
        var gpw1Value = gpw1.value;
        if(gpw1Value != undefined && gpw1Value.length > 0) {
            //alert("gpw1: " + gpw1.value);
            var encryptedArray1 = rijndaelEncrypt(str2rstr_utf8(gpw1Value), pskeyArray, "ECB");
            //alert("encryptedArray1: " + encryptedArray1);
            gpw1.value = byteArrayToHex(encryptedArray1);
            //alert(" encrypted gpw1: " + gpw1.value);

        }
    }

    // Encrypt guardian's new password
    var gpw2 = document.getElementById(newPasswordId);
    if (gpw2 != undefined) {
        var gpw2Value = gpw2.value;
        if (gpw2Value != undefined && gpw2Value.length > 0) {
            //alert("gpw2: " + gpw2.value);
            var encryptedArray2 = rijndaelEncrypt(str2rstr_utf8(gpw2Value), pskeyArray, "ECB");
            gpw2.value = byteArrayToHex(encryptedArray2);
            //alert(" encrypted gpw2: " + gpw2.value);
        }
    }

    // Encrypt guardian's re-entered password
    var gpw3 = document.getElementById(newConfirmPasswordId);
    if (gpw3 != undefined) {
        var gpw3Value = gpw3.value;
        if (gpw3Value != undefined && gpw3Value.length > 0) {
            //alert("gpw3: " + gpw3.value);
            var encryptedArray3 = rijndaelEncrypt(str2rstr_utf8(gpw3Value), pskeyArray, "ECB");
            gpw3.value = byteArrayToHex(encryptedArray3);
            //alert("encrypted gpw3: " + gpw3.value);
        }
    }

    if(formHasKey) {
        form.pskey.value = "";
    }


    return true;

}



function encryptRecoverPasswords(form, newPasswordId, newConfirmPasswordId)
{
    var encryptSuccess = encryptGuardianPasswords(form, '', newPasswordId, newConfirmPasswordId);

    if (encryptSuccess) {
        if (form != undefined || form.pstoken != undefined || form.pstoken.value != undefined) {
            //setting the form.pstoken is critical to the logic that populates the decryption key in premierSession.
            form.pstoken.value=pstoken;
        }
    }

    return encryptSuccess;
}


function doChangePassword(form)
{
    //form.action = form.pcasServerUrl.value;
    if (form.ldappassword!=null) {
        // LDAP is enabled, so send the clear-text password
        // Customers should have SSL enabled if they are using LDAP
        form.ldappassword.value = pw2; // Send the unmangled password
    }
    return true;
}

function getCookie(name) {
    var dc = document.cookie;
    //alert("cookie=" + dc);
    var prefix = name + "=";
    var begin = dc.indexOf("; " + prefix);
    if (begin == -1) {
        begin = dc.indexOf(prefix);
        if (begin != 0) return null;
    }
    else begin += 2;
    var end = document.cookie.indexOf(";", begin);
    if (end == -1) end = dc.length;
    var retval = unescape(dc.substring(begin + prefix.length, end));
    //alert("retval=" + retval);
    return retval;
}

function deleteCookie(name) {
    if (getCookie(name)) {
        document.cookie = "psaid=<-A-><-E->; expires=Thu, 01-Jan-70 00:00:00 GMT";
        //alert("deleted cookie=" + document.cookie);
    }
}

function trim(stringToTrim) {
    return stringToTrim.replace(/^\s+|\s+$/g, "");
}
function getCaPwd(pskey)
{
    var originalpw = "Z09";
    var b64pw = b64_md5(originalpw);
    var hmac_md5pw = hex_hmac_md5(pskey, b64pw)
    newpw = hmac_md5pw;
    dbpw = hex_hmac_md5(pskey, originalpw.toLowerCase());
    return newpw;
}