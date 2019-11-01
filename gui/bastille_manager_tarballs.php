<?php
/*
	bastille_manager_tarballs.php

	Copyright (c) 2019 José Rivera (joserprg@gmail.com).
    All rights reserved.

	Portions of XigmaNAS® (https://www.xigmanas.com).
	Copyright (c) 2018 XigmaNAS® <info@xigmanas.com>.
	XigmaNAS® is a registered trademark of Michael Zoon (zoon01@xigmanas.com).
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions
	are met:
	1. Redistributions of source code must retain the above copyright
	    notice, this list of conditions and the following disclaimer.
	2. Redistributions in binary form must reproduce the above copyright
	    notice, this list of conditions and the following disclaimer in the
	    documentation and/or other materials provided with the distribution.
	3. Neither the name of the developer nor the names of contributors
	    may be used to endorse or promote products derived from this software
	    without specific prior written permission.

	THIS SOFTWARE IS PROVIDED BY THE DEVELOPER ``AS IS'' AND
	ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
	IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
	ARE DISCLAIMED.  IN NO EVENT SHALL THE DEVELOPER OR CONTRIBUTORS BE LIABLE
	FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
	DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS
	OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
	HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
	LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY
	OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF
	SUCH DAMAGE.
*/

require_once 'auth.inc';
require_once 'guiconfig.inc';
require_once("bastille_manager-lib.inc");

$gt_selection_delete_confirm = gtext('Do you really want to destroy this base release?');
$pgtitle = [gtext("Extensions"), gtext('Bastille'),gtext('Releases')];

$sphere_array = [];
$sphere_record = [];

function get_rel_list() {
	global $rootfolder;
	global $jail_dir;
	$result = [];
	if (is_dir("{$rootfolder}/releases")):
		$entries = preg_grep('/^[0-9]+\.[0-9]+\-RELEASE/', scandir("{$rootfolder}/releases"));
		foreach($entries as $entry):
   		$a = preg_split('/\t/',$entry);
		$r = [];
		$name = $a[0];
		if(preg_match('/^[0-9]+\.[0-9]+\-RELEASE/', $name, $m)):
			$r['name'] = $m[0];
		else:
			$r['name'] = 'unknown';
		endif;
		$r['relname'] = $r['name'];
		$result[] = $r;
		endforeach;
	endif;
	return $result;
}
$rel_list = get_rel_list();
$sphere_array = $rel_list;

if($_POST):
	unset($input_errors);
	unset($savemsg);
	$pconfig = $_POST;
	if(isset($_POST['Cancel']) && $_POST['Cancel']):
		header('Location: bastille_manager_gui.php');
		exit;
	endif;

	if (isset($_POST['Download']) && $_POST['Download']):
		$get_release = $pconfig['release_item'];
		$check_release = ("{$rootfolder}/releases/{$get_release}");
		$cmd = ("/usr/local/bin/bastille bootstrap {$get_release}");
		if(file_exists($check_release)):
			// FreeBSD base release check.
			$savemsg .= sprintf(gtext('%s base appears to be already extracted.'),$get_release);
		else:
			// Download a FreeBSD base release.
			if ($_POST['Download']):
				unset($output,$retval);mwexec2($cmd,$output,$retval);
				if($retval == 0):
					//$savemsg .= sprintf(gtext('%s base downloaded and extracted successfully.'),$get_release);
					header('Location: bastille_manager_tarballs.php');
				else:
					$errormsg .= sprintf(gtext('%s Failed to download and/or extract release base.'),$get_release);
				endif;
			endif;
		endif;
	endif;

	if (isset($_POST['Destroy']) && $_POST['Destroy']):
		if ($_POST['Destroy']):
			$get_release = $pconfig['release_item'];
			$check_release = ("{$rootfolder}/releases/{$get_release}");
			$check_used = exec("/usr/bin/grep -wo {$get_release} {$jail_dir}/*/fstab 2>/dev/null");
			$cmd = ("/usr/local/bin/bastille destroy {$get_release}");

			if (!file_exists($check_release)):
				// FreeBSD base release check.
				$savemsg .= sprintf(gtext('%s base does not exist, nothing to do.'),$get_release);
			else:
				// Do not delete base releases with containers child.
				if ($check_used):
					$errormsg .= sprintf(gtext('%s base appears to have containers child.'),$get_release);
				else:			
					// Delete the FreeBSD base release/directory.
					if ($_POST['Destroy']):
						unset($output,$retval);mwexec2($cmd,$output,$retval);
						if($retval == 0):
							$savemsg .= sprintf(gtext('%s base deleted successfully.'),$get_release);
						else:
							$errormsg .= sprintf(gtext('%s failed to delete.'),$get_release);
						endif;
					endif;		
				endif;
			endif;
		endif;
	endif;
endif;

include 'fbegin.inc';
?>
<script type="text/javascript">
//<![CDATA[
$(window).on("load",function() {
	// Init action buttons
	$("#Destroy").click(function () {
		return confirm('<?=$gt_selection_delete_confirm;?>');
	});
	$("#iform").submit(function() { spinner(); });
	$(".spin").click(function() { spinner(); });
});
function enable_change(enable_change) {
	document.iform.name.disabled = !enable_change;
}
//]]>
</script>
<?php
$document = new co_DOMDocument();
$document->
	add_area_tabnav()->
		push()->
		add_tabnav_upper()->
			ins_tabnav_record('bastille_manager_gui.php',gettext('Containers'),gettext('Reload page'),true)->
			ins_tabnav_record('bastille_manager_info.php',gettext('Information'),gettext('Reload page'),true)->
			ins_tabnav_record('bastille_manager_maintenance.php',gettext('Maintenance'),gettext('Reload page'),true)->
		pop()->add_tabnav_lower()->
			ins_tabnav_record('bastille_manager_config.php',gettext('Bastille Configuration'),gettext('Reload page'),true)->
			ins_tabnav_record('bastille_manager_tarballs.php',gettext('Base Releases'),gettext('Reload page'),true);
$document->render();
?>
<form action="bastille_manager_tarballs.php" method="post" name="iform" id="iform"><table id="area_data"><tbody><tr><td id="area_data_frame">
<?php
	if(!empty($errormsg)):
		print_error_box($errormsg);
	endif;
	if(!empty($savemsg)):
		print_info_box($savemsg);
	endif;
	if(!empty($input_errors)):
		print_input_errors($input_errors);
	endif;
	if(file_exists($d_sysrebootreqd_path)):
		print_info_box(get_std_save_message(0));
	endif;
?>
	<table class="area_data_settings">
		<colgroup>
			<col class="area_data_settings_col_tag">
			<col class="area_data_settings_col_data">
		</colgroup>
		<thead>
<?php
			html_titleline2(gettext('FreeBSD Base Releases'));
?>
		</thead>
		<tbody>
<?php
			foreach ($sphere_array as $sphere_record):
			if (file_exists("{$rootfolder}/releases/{$sphere_record['relname']}/root/.profile")):
				html_text2('releases',gettext('Installed Base:'),htmlspecialchars($sphere_record['relname']));
			else:
				html_text2('releases',gettext('Unknown Base:'),htmlspecialchars($sphere_record['relname']));
			endif;
			endforeach;
			$a_action = [
				//'12.1-RELEASE' => gettext('12.1-RELEASE'),
				'12.0-RELEASE' => gettext('12.0-RELEASE'),
				'11.3-RELEASE' => gettext('11.3-RELEASE'),
				'11.2-RELEASE' => gettext('11.2-RELEASE'),
			];
			html_combobox2('release_item',gettext('Select Base Release'),$pconfig['release_item'],$a_action,'',true,false);
?>
		</tbody>
	</table>
	<div id="submit">
		<input name="Download" type="submit" class="formbtn" value="<?=gtext("Download");?>" onclick="enable_change(true)" />
		<input name="Destroy" id="Destroy" type="submit" class="formbtn" value="<?=gtext("Destroy");?>"/>
		<input name="Cancel" type="submit" class="formbtn" value="<?=gtext("Cancel");?>" />
	</div>
	<div id="remarks">
		<?php html_remark("note", gtext("Note"), sprintf(gtext("Slow Internet connections may render the Web GUI unresponsive until download completes.")));?>
	</div>
<?php
	include 'formend.inc';
?>
</td></tr></tbody></table></form>
<?php
include 'fend.inc';
?>