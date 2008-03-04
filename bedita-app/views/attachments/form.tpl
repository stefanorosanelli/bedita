<script type="text/javascript">
<!--
{literal}
var langs = {{/literal}{foreach name=i from=$conf->langOptions key=lang item=label}"{$lang}":   "{$label}" {if !($smarty.foreach.i.last)},{/if}{/foreach}{literal}} ;
var validate = null ;
$(document).ready(function(){
    $.datepicker.setDefaults({
        showOn: 'both', 
        buttonImageOnly: true, 
	    buttonImage: '{/literal}{$html->webroot}img/calendar.gif{literal}', 
        buttonText: 'Calendar',
        dateFormat: '{/literal}{$conf->dateFormatValidation|replace:'yyyy':'yy'}{literal}',
        beforeShow: customRange
    }, $.datepicker.regional['{/literal}{$currLang}{literal}']); 
    $('#start').attachDatepicker();
    $('#end').attachDatepicker();
    // submit delete
	$("#delBEObject").submitConfirm({
		{/literal}
		action: "{$html->url('delete/')}",
		message: "{t}Are you sure that you want to delete the attachment?{/t}"
		{literal}
	});

});
{/literal}
//-->
</script>
<div id="containerPage">
<form action="{$html->url('/attachments/save')}" method="post" name="updateForm" id="updateForm" class="cmxform">
<input  type="hidden" name="data[id]" value="{$object.id|default:''}" />
<input  type="hidden" name="data[object_type_id]" value="{$object.object_type_id|default:''}" />
{include file="../pages/form_header.tpl"}
<div class="blockForm" id="errorForm"></div>
{include file="../pages/form_properties.tpl" doctype=false publication=false}
{include file="../pages/form_file.tpl"}
{include file="../pages/form_subtitle_desc.tpl"}
{include file="../pages/form_custom_properties.tpl" el=$object}
{include file="../pages/form_permissions.tpl" el=$object recursion=true}
</form>
</div>