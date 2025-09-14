<?php

/**
 * Connector form partial
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") || exit;

?>

<!-- An absolute position placed invisible form element which is out of browser window -->
<form action="placeholder_will_be_replaced" method="get" id="redirect-to-remote-upgrade-endpoint">
    <input type="hidden" name="oth" id="form-oth" value="">
    <input type="hidden" name="license_key" id="form-key" value="">
    <input type="hidden" name="version" id="form-version" value="">
    <input type="hidden" name="redirect" id="form-redirect" value="">
    <input type="hidden" name="endpoint" id="form-endpoint" value="">
    <input type="hidden" name="siteurl" id="form-siteurl" value="">
    <input type="hidden" name="homeurl" id="form-homeurl" value="">
    <input type="hidden" name="file" id="form-file" value="">
</form>
