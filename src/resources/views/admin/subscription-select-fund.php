<?php

use GiveFunds\Models\Fund;

defined( 'ABSPATH' ) or exit;
?>
<tr>
	<td>
		<strong>
			<label for="give-funds-subscription-select-fund">
				<?php esc_html_e( 'Associated Fund', 'give-recurring' ); ?>
			</label>
		</strong>
	</td>
	<td>
		<div class="give-funds-subscription-select-fund-wrap">
			<select id=give-funds-subscription-select-fund" name="give-selected-fund" class="give-select">
				<?php
				/* @var Fund[] $funds*/
				foreach ( $funds as $fund ) :
					?>
					<option value="<?php echo $fund->getId(); ?>"<?php echo $selectedFund === $fund->getId() ? ' selected' : ''; ?>>
						<?php echo $fund->getTitle(); ?>
						<?php if ( $fund->isDefault() ) : ?>
							( <?php esc_html_e( 'Default fund', 'give-recurring' ); ?> )
						<?php endif; ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>
	</td>
</tr>
