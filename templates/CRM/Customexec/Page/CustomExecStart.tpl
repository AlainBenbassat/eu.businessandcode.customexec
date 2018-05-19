<p>Found: {$found}</p>
<p>Not found: {$notfound}</p>
<p>Multi: {$multiple}</p>

<h3>Errors</h3>
<ul>
{section name=id loop=$errorMessage}
    <li>{$errorMessage[id]}</li>
{/section}
</ul>




