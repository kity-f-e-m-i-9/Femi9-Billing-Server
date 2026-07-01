<!------<input type="text" onkeypress="restrictusername(event)">----->
<script>
function restrictusername(event) {
            // Allowable characters: letters, numbers, dash, dot, comma, and at symbol
            const regex = /^[0-9]*$/;
            const key = String.fromCharCode(event.which);
            // Prevent input if the key doesn't match the regex
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		function restrictlandline(event) {
            // Allowable characters: letters, numbers, dash, dot, comma, and at symbol
            const regex = /^[0-9 +-]*$/;
            const key = String.fromCharCode(event.which);
            // Prevent input if the key doesn't match the regex
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		function restrictCountryName(event) {
            // Allowable characters: letters, numbers, dash, dot, comma, and at symbol
            const regex = /^[a-zA-Z]*$/;
            const key = String.fromCharCode(event.which);
            // Prevent input if the key doesn't match the regex
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		function restrictCountryCode(event) {
            // Allowable characters: letters, numbers, dash, dot, comma, and at symbol
            const regex = /^[0-9+]*$/;
            const key = String.fromCharCode(event.which);
            // Prevent input if the key doesn't match the regex
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
        function restrictpassword(event) {
            const regex = /^[a-zA-Z0-9@.,-]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
function restrictnumber(event) {
            const regex = /^[0-9]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		/*onkeypress="restrictSpecialChars(event)"*/
        function restrictSpecialChars(event) {
            const regex = /^[a-zA-Z0-9@., -]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		function restrictGSTIN(event) {
            const regex = /^[A-Z0-9]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		function restrictmobile(event) {
            const regex = /^[0-9]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		function restrictemail(event) {
            const regex = /^[a-zA-Z0-9@.]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		function restrictHSN(event) {
            const regex = /^[a-zA-Z0-9]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
		
		function restrictpincode(event) {
            const regex = /^[a-zA-Z0-9-]*$/;
            const key = String.fromCharCode(event.which);
            if (!regex.test(key)) {
                event.preventDefault();
            }
        }
		
    </script>