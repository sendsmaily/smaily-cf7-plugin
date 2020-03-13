<?php
/**
 * Content of Smaily for Contact Form 7 tab.
 *
 * @package Smaily for Contact Form 7
 * @author  Smaily
 */

?>
<h1 class='form-text text-muted' style='display:block;'>
	<?php echo esc_html__( 'Saving credentials links current form to Smaily', 'smaily-for-cf7' ); ?></h1>
<div id='smailyforcf7-credentials-validated'>
	<p id='smailyforcf7-credentials-error' class='smailyforcf7-response'
		style='padding:15px; background-color:#f2dede; margin:0 0 10px; display: none;'>
	</p>
	<p id='smailyforcf7-credentials-success' class='smailyforcf7-response'
		style='padding:15px; background-color:#dff0d8; margin:0 0 10px; display: none;'>
	</p>
</div>
<table class='form-table'>
	<tbody>
		<tr id='smailyforcf7-autoresponders' class='form-field' <?php if ( empty( $autoresponder_list ) ) : ?>
			style='display: none;' <?php endif; ?>>
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
		</tr>
		<tr class='form-field'>
			<th><?php echo esc_html__( 'Subdomain', 'smaily-for-cf7' ); ?></th>
			<td>
				<input type='text' class='regular-text' name='smailyforcf7-subdomain' style='max-width:50%;'
					value='<?php echo esc_html( $subdomain ); ?>' />
				<small class='form-text text-muted' style='display:block;'>
					For example <strong>'demo'</strong> from https://<strong>demo</strong>.sendsmaily.net/
				</small>
			</td>
		</tr>
		<tr class='form-field'>
			<th><?php echo esc_html__( 'Username', 'smaily-for-cf7' ); ?></th>
			<td>
				<input type='text' class='regular-text' name='smailyforcf7-username' style='max-width:50%;'
					value='<?php echo esc_html( $username ); ?>' />
			</td>
		</tr>
		<tr class='form-field'>
			<th><?php echo esc_html__( 'Password', 'smaily-for-cf7' ); ?></th>
			<td>
				<input type='password' class='regular-text' name='smailyforcf7-password' style='max-width:50%;'
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
			<th>
				<input id='smailyforcf7_remove_credentials' type='button'
					value='<?php echo esc_html__( 'Reset credentials', 'smaily-for-cf7' ); ?>' name='Clear'
					class='button' />
			</th>
		</tr>
	</tbody>
</table>
