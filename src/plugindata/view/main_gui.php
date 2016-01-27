<div class="wrap">

    <h2>Passive Indexation Check</h2>

    <table class="form-table">
        <tbody>
            <tr valign="top">   
            <th scope="row"><label for="passiveIndexationCheckDays">Notify me when GoogleBot doesn't visit the blog for</label></th>
            <td>
                <!-- Days for notification -->
                <select id="passiveIndexationCheckDays" name="passive_indexation_check_days">
                    <option value="1" <?php selected($options['notificationTime'], 1); ?>>1 day</option>
                    <option value="5" <?php selected($options['notificationTime'], 5); ?>>5 days</option>
                    <option value="10" <?php selected($options['notificationTime'], 10); ?>>10 days</option>
                    <option value="14" <?php selected($options['notificationTime'], 14); ?>>14 days</option>
                    <option value="30" <?php selected($options['notificationTime'], 30); ?>>30 days</option>
                    <option value="60" <?php selected($options['notificationTime'], 60); ?>>60 days</option>
                </select>
            </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="passiveIndexationCheckEmails">Notification email(s)</label></th>
            <td>
                <!-- Notification emails -->
                <input name="passive_indexation_check_emails" id="passiveIndexationCheckEmails" type="text" />
                <a onclick="passiveIndexationCheckJS.addEmail();"><span class="dashicons dashicons-plus-alt" style="line-height: 1.5; color: #000;"></a></span>
                <br>
                <div id="passiveIndexationCheckEmailsList" style="margin-top: 5px;">
                    <?php foreach ($options['notificationEmails'] as $key => $email) : ?>
                        <span><?php echo $email; ?><span>
                        <a onclick="passiveIndexationCheckJS.deleteEmail('<?php echo $email; ?>')">
                            <span class="dashicons dashicons-no-alt" style="color: #d9534f;"></span>
                        </a>
                        <br>
                    <?php endforeach; ?>
                </div>
            </td>
            </tr>
        </tbody>
    </table>

    <!-- Nonce value -->
    <input type="hidden" name="passive_indexation_check_nonce" id="passiveIndexationCheckNonce" value="<?php echo $nonce; ?>">
    <p class="submit"><input type="button" class="button button-primary" name="submit" value="Save" onclick="passiveIndexationCheckJS.updateSettings();" /></p>

</div>