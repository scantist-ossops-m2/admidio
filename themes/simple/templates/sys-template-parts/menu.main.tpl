{* Create the sidebar menu out of the navigation menu array *}
<div class="admidio-headline-mobile-menu d-md-none p-2">
    <span class="text-uppercase">{$l10n->get("SYS_MENU")}</span>
    <button class="btn btn-link d-md-none collapsed float-end" type="button" data-bs-toggle="collapse"
            data-bs-target="#adm_main_menu" aria-controls="adm_main_menu" aria-expanded="false">
        <i class="bi bi-list"></i>
    </button>
</div>
<nav id="adm_main_menu" class="admidio-menu-list collapse">
    {foreach $menuNavigation as $menuGroup}
        <div class="admidio-menu-header">{$menuGroup.name}</div>
        <ul class="nav admidio-menu-node flex-column mb-0">
            {foreach $menuGroup.items as $menuItem}
                <li class="nav-item">
                    <a id="{$menuItem.id}" class="nav-link" href="{$menuItem.url}">
                        <i class="{$menuItem.icon}"></i>{$menuItem.name}
                        {if $menuItem.badgeCount > 0}
                            <span class="badge bg-light text-dark">{$menuItem.badgeCount}</span>
                        {/if}
                    </a>
                </li>
            {/foreach}
        </ul>
    {/foreach}
</nav>
