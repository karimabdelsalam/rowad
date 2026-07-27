<?php
/*
 * Smarty plugin
 * ————————————————————-
 * File:     function.SEO.php
 * Type:     function
 * Name:     Seoit
 * Purpose:  Convert url to SEO url
 * ————————————————————-
 */

function smarty_modifier_SEO($string)
{
  $code_entities_match = array('-', '--', '&quot;', '!', '@', '#', '$', '%', '^', '&', '*', '(', ')', '_', '+', '{', '}', '|', ':', '"', '<', '>', '?', '[', ']', '\\', ';', "'", ',', '.', '/', '*', '+', '~', '`', '=');
  return preg_replace('/\s+/', '_', str_replace($code_entities_match, ' ', mb_strtolower($string, 'UTF-8')));
}

?>