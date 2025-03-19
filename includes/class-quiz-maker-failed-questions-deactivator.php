<?php
/**
 * Fired during plugin deactivation.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

class Quiz_Maker_Failed_Questions_Deactivator {

    /**
     * Run on plugin deactivation.
     *
     * @since    1.0.0
     */
    public static function deactivate() {
        // We don't want to delete the tables on deactivation
        // This ensures user data is preserved
    }
}