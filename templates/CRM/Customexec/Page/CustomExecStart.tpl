{section name=id loop=$group}
<h3>{$group[id]}</h3>
<p>Found: {$found[id]}</p>
<p>Not found: {$notfound[id]}</p>
<p>Multi: {$multiple[id]}</p>
<p>{$errorMessage[id]}</p>
{/section}

