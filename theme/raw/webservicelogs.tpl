{include file="header.tpl"}

    <form action="{$WWWROOT}artefact/webservice/webservicelogs.php" method="post">
        <div class="searchform">
            <label>{str tag='userauth' section='artefact.webservice'}:</label>
            <input type="text" name="userquery" id="query"{if $search->userquery} value="{$search->userquery}"{/if}>
            {if count($institutions) > 1}
            <span class="institutions">
                <label>{str tag='Institution' section='admin'}:</label>
                    {if $USER->get('admin')}
                    <select name="institution" id="institution">
                    {else}
                    <select name="institution_requested" id="institution_requested">
                    {/if}
                        <option value="all"{if !$.request.institution} selected="selected"{/if}>{str tag=All}</option>
                        {foreach from=$institutions item=i}
                        <option value="{$i->name}"{if $i->name == $.request.institution}" selected="selected"{/if}>{$i->displayname}</option>
                        {/foreach}
                    </select>
            </span>
            {/if}
            <span class="institutions">
                <label>{str tag='protocol' section='artefact.webservice'}:</label>
                    <select name="protocol" id="protocol">
                        <option value="all"{if !$.request.protocol} selected="selected"{/if}>{str tag=All}</option>
                        {foreach from=$protocols item=i}
                        <option value="{$i}"{if $i == $.request.protocol}" selected="selected"{/if}>{$i}</option>
                        {/foreach}
                    </select>
            </span>            
            <span class="institutions">
                <label>{str tag='sauthtype' section='artefact.webservice'}:</label>
                    <select name="authtype" id="authtype">
                        <option value="all"{if !$.request.authtype} selected="selected"{/if}>{str tag=All}</option>
                        {foreach from=$authtypes item=i}
                        <option value="{$i}"{if $i == $.request.authtype}" selected="selected"{/if}>{$i}</option>
                        {/foreach}
                    </select>
            </span>        
            <label>{str tag='function' section='artefact.webservice'}:</label>
            <input type="text" name="functionquery" id="query"{if $search->functionquery} value="{$search->functionquery}"{/if}>
            <button id="query-button" class="btn-search" type="submit">{str tag="go"}</button>
            <br/>
            <label>{str tag='errors' section='artefact.webservice'}:</label>
            <input type="checkbox" name="onlyerrors" id="query"{if $search->onlyerrors} CHECKED{/if}>
        </div>
        <div id="results" class="section">
            {$results|safe}
        </div>
    </form>

{include file="footer.tpl"}
