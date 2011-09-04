{if $USER->get('admin')}
<a href="{$WWWROOT}admin/users/edit.php?id={$r.id}" title="{$r.firstname} {$r.lastname} ({$r.email})">{$r.username}</a>
{else}
<a href="{$WWWROOT}user/view.php?id={$r.id}" title="{$r.firstname} {$r.lastname} ({$r.email})">{$r.username}</a>
{/if}
