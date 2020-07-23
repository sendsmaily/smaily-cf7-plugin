(function( $ ) {
	function getUrlParam(name) {
		var results = new RegExp('[\?&]' + name + '=([^&#]*)').exec(window.location.href);
		if (results == null) {
			return null;
		}
		else {
			return decodeURI(results[1]) || 0;
		}
	}
	$(document).ready(function () {
		// Disable onbeforeunload event.
		jQuery(window).off("beforeunload", null);

		$('#smailyforcf7_validate_credentials').click(function () {
			$.post(smaily_for_cf7.ajax_url,
			{
				// wp ajax action
				action: 'verify_credentials_callback',

				// vars
				subdomain: $('input[name="smailyforcf7[subdomain]"]').val(),
				username: $('input[name="smailyforcf7[username]"]').val(),
				password: $('input[name="smailyforcf7[password]"]').val(),

				// Can't use WPCF7_ContactForm in callback, get form (post) ID with Ajax.
				form_id: getUrlParam('post'),
				// send the nonce along with the request
				nonce: smaily_for_cf7.nonce
			})
			.always(function (result) {
				$('.smailyforcf7-response').hide();
				if (result.code !== 200) {
					$('#smailyforcf7-credentials-error').text(result.message).show();
					return;
				}
				$('#smailyforcf7-credentials-invalidated').hide();
				// First option is 'No autoresponder'.
				$('#smailyforcf7-autoresponder-select').find('option:not(:first)').remove();
				$.each(result.autoresponders, function(id, autoresponder) {
					$('#smailyforcf7-autoresponder-select').append(new Option(autoresponder, id));
				});
				$('#smailyforcf7-credentials-valid').show();
			})
		});

		$('#smailyforcf7_remove_credentials').click(function () {
			$.post(
				smaily_for_cf7.ajax_url,
				{
					action: 'remove_credentials_callback',
					// Can't use WPCF7_ContactForm in callback, get form (post) ID with Ajax.
					form_id: getUrlParam('post'),
				},
				function (result) {
					if (result.code === 200) {
						// Clear credentials
						$('#smailyforcf7-credentials-valid').hide();
						$('input[name="smailyforcf7[subdomain]"]').val('');
						$('input[name="smailyforcf7[username]"]').val('');
						$('input[name="smailyforcf7[password]"]').val('');
						$('#smailyforcf7-credentials-invalidated').show();
					} else {
						$('#smailyforcf7-credentials-valid-message').text(result.message).show();
					}
				},
			);
			return false;
		});
	});
})( jQuery );
