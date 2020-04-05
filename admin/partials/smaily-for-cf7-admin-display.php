<?php
/**
 * Content of Smaily for Contact Form 7 tab.
 *
 * @package Smaily for Contact Form 7
 * @author  Smaily
 */

?>
<?php if ( ! isset( $_GET['post'] ) ) : ?>
<div id='form-id-unknown'>
	<p id='smailyforcf7-form-id-error' style='padding:15px; background-color:#f2dede; margin:0 0 10px;'>
		<?php echo esc_html__(
			'Configuring Smaily integration is disabled when using "Add New Form". Please save this form or edit an already existing form',
			'smaily-for-cf7'
		); ?>
	</p>
</div>
<?php else : ?>
<div id='smailyforcf7-credentials-valid' style='display:<?php echo $are_credentials_valid ? 'block' : 'none'; ?>'>
	<span  style='margin:15px;'><?php echo esc_html__( 'Your API credentials are valid', 'smaily-for-cf7' ); ?></span></br>
	<table class='autoresponders-table' style="margin:15px">
		<tr id='smailyforcf7-autoresponders' class='form-field'>
			<th><?php echo esc_html__( 'Autoresponder', 'smaily-for-cf7' ); ?></th>
			<td>
				<select id='smailyforcf7-autoresponder-select' name='smailyforcf7-autoresponder'>
					<option value=''><?php echo esc_html__( 'No autoresponder', 'smaily-for-cf7' ); ?></option>
					<?php foreach ( $autoresponder_list as $autoresponder_id => $autoresponder_title ) : ?>
					<option value='<?php echo esc_html( $autoresponder_id ); ?>'
						<?php if ( $default_autoresponder === $autoresponder_id ) : ?> selected='selected'
						<?php endif; ?>>
						<?php echo esc_html( $autoresponder_title ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</td>
			</th>
		</tr>
	</table>
	<input id='smailyforcf7_remove_credentials' type='button' style="margin:15px"
		value='<?php echo esc_html__( 'Reset credentials', 'smaily-for-cf7' ); ?>' name='Clear' class='button' />
</div>
<div id='smailyforcf7-credentials-invalidated' style='display:<?php echo $are_credentials_valid ? 'none' : 'block'; ?>'>
	<h1 class='form-text text-muted' style='display:block;'>
		<?php echo esc_html__( 'Connect form with Smaily', 'smaily-for-cf7' ); ?></h1>
	<p id='smailyforcf7-captcha-error'
		style='padding:15px; background-color:#ffdf92; margin:0 0 10px; display:<?php echo $captcha_enabled ? 'none' : 'block'; ?>'>
		<?php echo esc_html__( 'Captcha disabled. Please use a captcha if this is a public site.', 'smaily-for-cf7' ); ?>
	</p>
	<p id='smailyforcf7-credentials-error' class='smailyforcf7-response'
			style='padding:15px; background-color:#f2dede; margin:0 0 10px; display: none;'>
		</p>
	<table class='form-table'>
		<tbody>
			<tr class='form-field'>
				<th><?php echo esc_html__( 'Subdomain', 'smaily-for-cf7' ); ?></th>
				<td>
					<input type='text' class='regular-text' name='smailyforcf7[subdomain]' style='max-width:50%;'
						value='<?php echo esc_html( $subdomain ); ?>' />
					<small class='form-text text-muted' style='display:block;'>
						For example <strong>'demo'</strong> from https://<strong>demo</strong>.sendsmaily.net/
					</small>
				</td>
			</tr>
			<tr class='form-field'>
				<th><?php echo esc_html__( 'API Username', 'smaily-for-cf7' ); ?></th>
				<td>
					<input type='text' class='regular-text' name='smailyforcf7[username]' style='max-width:50%;'
						value='<?php echo esc_html( $username ); ?>' />
				</td>
			</tr>
			<tr class='form-field'>
				<th><?php echo esc_html__( 'API Password', 'smaily-for-cf7' ); ?></th>
				<td>
					<input type='password' class='regular-text' name='smailyforcf7[password]' style='max-width:50%;'
						value='<?php echo esc_html( $password ); ?>' />
				</td>
			</tr>
		</tbody>
	</table>
	<table>
		<tbody>
			<tr class='button-field'>
				<th>
					<input id='smailyforcf7_validate_credentials' type='button'
						value='<?php echo esc_html__( 'Verify credentials', 'smaily-for-cf7' ); ?>' name='Submit'
						class='button-primary' />
				</th>
			</tr>
		</tbody>
	</table>
</div>
<?php endif; ?>
