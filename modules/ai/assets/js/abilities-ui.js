if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function($) {
        if (typeof window.AbilityExplorer !== 'undefined') return; // script loaded by WP-AI
        
        $('#ability-test-invoke').on('click', function() {
            var ability = $(this).data('ability');
            var input = $('#ability-test-payload').val();
            
            var $resContainer = $('#ability-test-result-container');
            var $res = $('#ability-test-result');
            
            $resContainer.show();
            $res.html('<i>Loading...</i>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ai_ability_explorer_invoke',
                    _ajax_nonce: waiAbilitiesData.nonce,
                    ability: ability,
                    input: input
                },
                success: function(response) {
                    if (response.success) {
                        $res.html('<pre style="color:green;margin:0;">' + JSON.stringify(response.data.data, null, 2) + '</pre>');
                    } else {
                        $res.html('<pre style="color:red;margin:0;">' + JSON.stringify(response.data, null, 2) + '</pre>');
                    }
                },
                error: function() {
                    $res.html('<span style="color:red;">AJAX Error</span>');
                }
            });
        });
        
        $('#ability-test-clear').on('click', function() {
            $('#ability-test-result-container').hide();
            $('#ability-test-result').html('');
        });
        
        $('.ability-copy-btn').on('click', function() {
            var targetId = $(this).data('copy');
            var text = document.getElementById(targetId).innerText;
            navigator.clipboard.writeText(text).then(() => {
                var $btn = $(this);
                var origText = $btn.text();
                $btn.text('Copied!');
                setTimeout(function(){ $btn.text(origText); }, 2000);
            });
        });
    });
}
