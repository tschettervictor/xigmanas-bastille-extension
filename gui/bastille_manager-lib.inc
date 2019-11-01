<?php
/*
	bastille_manager-lib.inc

	Copyright (c) 2019 José Rivera (joserprg@gmail.com).
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

require_once 'super_fun.inc';
require_once 'globals.inc';
require_once 'array.inc';
require_once 'system.inc';

// Initialize some variables.
// TODO: Some infos can be gathered with-
// internal PHP functions rather than external shell commands.

//$rootfolder = dirname($config['rc']['postinit']['cmd'][$i]);
$restore_name = "restore";
$confdir = "/var/etc/bastille_conf";
$cwdir = exec("/usr/bin/grep 'INSTALL_DIR=' {$confdir}/conf/bastille_config | /usr/bin/cut -d'\"' -f2");
$rootfolder = $cwdir;
$configfile = "{$rootfolder}/conf/bastille_config";
$versionfile = "{$rootfolder}/version";
$date = strftime('%c');
$logfile = "{$rootfolder}/log/bastille_ext.log";
$logevent = "{$rootfolder}/log/bastille_last_event.log";
$backup_path = exec("/bin/cat {$configfile} | /usr/bin/grep 'BACKUP_DIR=' | /usr/bin/cut -d'\"' -f2");
$bastille_config = "{$rootfolder}/conf/bastille_config";
$jail_dir = "{$rootfolder}/jails";
$image_dir = "ext/bastille/images";

// Ensure the root directory is configured.
if ($rootfolder == "")
	$input_errors[] = gtext("Extension installed with fault");
else {
// Initialize locales.
	$textdomain = "/usr/local/share/locale";
	$textdomain_bastille = "/usr/local/share/locale-bastille";
	if (!is_link($textdomain_bastille)) { mwexec("ln -s {$rootfolder}/locale-bastille {$textdomain_bastille}", true); }
	bindtextdomain("xigmanas", $textdomain_bastille);
}
if (is_file("{$rootfolder}/postinit")) unlink("{$rootfolder}/postinit");

// Get all base releases list.
function get_all_release_list() {
	global $rootfolder;
	global $g;
	exec("/bin/echo; /bin/ls {$rootfolder}/releases 2>/dev/null | /usr/bin/tr -s ' ' '\n'",$relinfo);
	array_shift($relinfo);
	$rellist = [];
	foreach($relinfo as $rel):
		$arel = preg_split("/\s+/",ltrim($rel));
		$relname = chop($arel[0]);
		if(substr($relname,-1) === '*'):
			$relname = substr($relname,0,strlen($relname) - 1);
		endif;
		$rellist[$relname] = [];
	endforeach;
	return $rellist;
}

// Get all interface list.
function get_all_interface_list() {
	global $g;
	exec("/bin/echo; /sbin/ifconfig -l | /usr/bin/tr -s ' ' '\n'; /bin/echo 'NONE'",$linkinfo);
	array_shift($linkinfo);
	$iflist = [];
	foreach($linkinfo as $link):
		$alink = preg_split("/\s+/",ltrim($link));
		$ifname = chop($alink[0]);
		if(substr($ifname,-1) === '*'):
			$ifname = substr($ifname,0,strlen($ifname) - 1);
		endif;
		$iflist[$ifname] = [];
	endforeach;
	return $iflist;
}

// Get jail infos.
function get_jail_infos() {
	global $img_path;
	global $image_dir;
	global $configfile;
	global $jail_dir;
	$result = [];
	if(is_dir($jail_dir)):
		$cmd = '/usr/local/bin/bastille list jail 2>&1';
	else:
		$cmd = ":";
	endif;
	mwexec2($cmd,$rawdata);
	foreach($rawdata as $line):
		$a = preg_split('/\t/',$line);
		$r = [];
		$name = $a[0];
		if(preg_match('/(.*)/', $name, $m)):
			$r['name'] = $m[1];
		else:
			$r['name'] = '-';
		endif;
		$r['jailname'] = $r['name'];

		// Set the JID on the running jails.
		$item = $r['jailname'];
		$r['id'] = exec("/usr/sbin/jls | /usr/bin/grep {$item} | /usr/bin/awk '{print $1}'");
		if (!$r['id']):
			$r['id'] = "-";
		endif;
		// Set the IPv4 on the running jails.
		//$r['ip'] = exec("/usr/sbin/jls | /usr/bin/grep {$item} | /usr/bin/awk '{print $2}'");
		$r['ip'] = exec("/usr/bin/grep -w 'ip4.addr' {$jail_dir}/{$item}/jail.conf | /usr/bin/awk '{print $3}' | /usr/bin/tr -d ';'");
		if (!$r['ip']):
			$r['ip'] = "-";
		endif;
		// Display interfaces.
		$r['nic'] = exec("/usr/bin/grep -w 'interface' {$jail_dir}/{$item}/jail.conf | /usr/bin/awk '{print $3}' | /usr/bin/tr -d ';'");
		if (!$r['nic']):
			$r['nic'] = "-";
		endif;
		// Display path.
		$r['path'] = exec("/usr/bin/grep -w 'path' {$jail_dir}/{$item}/jail.conf | /usr/bin/awk '{print $3}' | /usr/bin/tr -d ';'");
		if (!$r['path']):
			$r['path'] = "-";
		endif;
		// Display auto-start settings.
		$jail_autostart = exec("/usr/sbin/sysrc -qn -f {$configfile} {$item}_AUTO_START");
		if ($jail_autostart == 'YES') {
			$r['boot'] = $img_path['ena'];
		} elseif ($jail_autostart == 'NO') {
			$r['boot'] = $img_path['dis'];
		} else {
			$r['boot'] = $img_path['dis'];
		}
		// Display running status icons.
		$jail_running = exec("/usr/sbin/jls | /usr/bin/grep -w {$item}");
		if ($jail_running):
			$r['stat'] = $img_path['ena'];
		else:
			$r['stat'] = $img_path['dis'];
		endif;
		// Display custom template icons if available.
		$item = $item;
		$template_icon = "{$image_dir}/{$item}_icon.png";
		if(file_exists($template_icon)):
			$r['logo'] = "{$image_dir}/{$item}_icon.png";
		else:
			// Display standard FreeBSD icon.
			$r['logo'] = "{$image_dir}/bsd_icon.png";
		endif;

		$result[] = $r;
	endforeach;
	return $result;
}

?>