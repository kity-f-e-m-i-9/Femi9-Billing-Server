<script>
function restrictusername(event) { const r=/^[0-9]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictlandline(event) { const r=/^[0-9 +-]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictCountryName(event) { const r=/^[a-zA-Z]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictCountryCode(event) { const r=/^[0-9+]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictpassword(event) { const r=/^[a-zA-Z0-9@.,-]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictnumber(event) { const r=/^[0-9]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictSpecialChars(event) { const r=/^[a-zA-Z0-9@., -/]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictGSTIN(event) { const r=/^[A-Z0-9]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictmobile(event) { const r=/^[0-9]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictemail(event) { const r=/^[a-zA-Z0-9@.]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictHSN(event) { const r=/^[a-zA-Z0-9]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
function restrictpincode(event) { const r=/^[a-zA-Z0-9-]*$/; if(!r.test(String.fromCharCode(event.which))) event.preventDefault(); }
</script>
