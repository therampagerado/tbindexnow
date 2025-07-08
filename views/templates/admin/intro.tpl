<div class="panel">
    <h3><i class="icon-info"></i> {$module->displayName}</h3>
    <p>
        <a href="https://www.indexnow.org/" target="_blank">IndexNow</a> protocol allows instant notification of URL changes to search engines. The module looks for product changes and builds a list to be submitted to search engines. The module is multistore and multilanguage enabled.
    </p>
    <p>
        <strong>{$module->l('Configuration:')}</strong><br>
        {$module->l('1. Generate your API Key at')} <a href="https://www.bing.com/indexnow" target="_blank">Bing IndexNow</a>. You'll need one key even if you run a multistore installation.&nbsp;<br>
        {$module->l('2. Save the key in the form below to automatically create the .txt key file in your shop root - no manual upload needed.')}&nbsp;<br>
        {$module->l('3. Use the Cron URL(s) below to submit pending URLs to search engines.')}&nbsp;<br>
        {$module->l('4. View submission history and manage queued URLs in the list bellow.')}
    </p>
</div>