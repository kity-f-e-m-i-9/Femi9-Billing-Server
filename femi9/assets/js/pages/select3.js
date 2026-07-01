/*$(document).ready(function() {
    
    "use strict";
    
    $('select').select2();
    
    $(".js-example-basic-multiple-limit").select2({
        maximumSelectionLength: 2
    });
    
    $(".js-example-tokenizer").select2({
        tags: true,
        tokenSeparators: [',', ' ']
    });
});*/


$(document).ready(function() {
    
    "use strict";
    
    // Initialize only specific select boxes with select2 by targeting their unique classes or IDs

    // Initialize for a specific select box with class "my-select"
    $('.my-select').select2();

    // Initialize with maximum selection length for a specific select box
    $(".js-example-basic-multiple-limit").select2({
        maximumSelectionLength: 2
    });
    
    // Initialize with token separators for a specific select box
    $(".js-example-tokenizer").select2({
        tags: true,
        tokenSeparators: [',', ' ']
    });
});
