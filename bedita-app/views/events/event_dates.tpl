{$html->css("jquery.timepicker.css")}
{$javascript->link("jquery.placement.below")}
{$javascript->link("jquery.timepicker-list")}

{literal}
<script type="text/javascript">
$(document).ready(function(){
	$('#eventStart').attachDatepicker();
	$('#eventEnd').attachDatepicker();
	$("#timeStart, #timeEnd").timePicker();
});
</script>
{/literal}
<h2 class="showHideBlockButton">{t}Event calendar{/t}</h2>
<div class="blockForm" id="eventDates">
<fieldset>
{assign var=idx value=0}
{*
{foreach key="name" item="d" from=$object.EventDateItem}
	<span style="font-weight:bold;">{t}Event start{/t}:</span>
	<input type="text" class="{literal}{checkDate:true}{/literal}" title="{t}start has to be a valid date in the following format:{/t} {$conf->dateFormatValidation}" 
		name="data[EventDateItem][{$name}][start]" id="eventStart{$name}" value="{if !empty($d.start)}{$d.start|date_format:$conf->date_format}{/if}"/>
	<span style="font-weight:bold;">{t}end{/t}:</span>
	<input type="text" class="{literal}{checkDate:true}{/literal}" title="{t}end has to be a valid date in the following format:{/t} {$conf->dateFormatValidation}" 
		name="data[EventDateItem][{$name}][end]" id="eventEnd{$name}" value="{if !empty($d.end)}{$d.end|date_format:$conf->date_format}{/if}"/>
	<hr/>
{/foreach}
*}
{assign var=d value=$object.EventDateItem.0}
	<span style="font-weight:bold;">{t}Add event date{/t}</span>
	<br/>
	<span style="font-weight:bold;">{t}start{/t}</span>
	<input type="text" class="{literal}{{/literal}checkDate:'{$conf->dateFormatValidation}'{literal}}{/literal}" title="{t 1=$conf->dateFormatValidation}Please enter a valid date in the %1 format{/t}" 
		name="data[EventDateItem][{$idx}][start]" id="eventStart" value="{if !empty($d.start)}{$d.start|date_format:$conf->date_format}{/if}"/>
	<input type="text" id="timeStart" name="data[EventDateItem][{$idx}][timeStart]" class="{literal}{checkTime: true}{/literal}"  
		title="{t 1='HH:mm'}Please enter a valid time in the %1 format{/t}" value="{if !empty($d.start)}{$d.start|date_format:"%H:%M"}{/if}" size="10"/>
	<span style="font-weight:bold;">{t}end{/t}:</span>
	{strip}
	<input type="text" class="{literal}{{/literal}
									checkDate: '{$conf->dateFormatValidation}',
									dateGreaterThen: new Array('{$conf->dateFormatValidation}','eventStart')
							  {literal}}{/literal}" 
						title="{t 1=$conf->dateFormatValidation}Please enter a valid date in the %1 format and greater than the previous date{/t}" 
		name="data[EventDateItem][{$idx}][end]" id="eventEnd" value="{if !empty($d.end)}{$d.end|date_format:$conf->date_format}{/if}"/>
	{/strip}
	<input id="timeEnd" name="data[EventDateItem][{$idx}][timeEnd]" type="text"  class="{literal}{checkTime: true}{/literal}"
		title="{t 1='HH:mm'}Please enter a valid time in the %1 format{/t}" value="{if !empty($d.end)}{$d.end|date_format:"%H:%M"}{/if}" size="10"/>
	<hr/>
</fieldset>
</div>