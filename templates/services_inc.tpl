{strip}
{assign var=serviceLocTpls value=$gLibertySystem->getServiceValues("content_`$serviceLocation`_tpl")}
{if $serviceLocTpls|@count > 0}
	{if !empty($liberty_service_content)}
		{if $serviceLocTpls && ( $serviceLocation == 'nav' || $serviceLocation == 'view' )}
			<div class="services-{$serviceLocation}">
		{/if}
		{foreach from=$serviceLocTpls key=serviceName item=template}
			{include file=$template serviceHash=$serviceHash}
		{/foreach}
		{if $serviceLocTpls && ( $serviceLocation == 'nav' || $serviceLocation == 'view' )}
			</div>
		{/if}
	{/if}
{/if}
{/strip}
