<?php
/* Copyright (C) 2007-2008 Jeremie Ollivier    <jeremie.o@laposte.net>
 * Copyright (C) 2011      Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2012      Marcos García       <marcosgdf@gmail.com>
 * Copyright (C) 2018      Andreu Bisquerra    <jove@bisquerra.com>
 * Copyright (C) 2019      Josep Lluís Amador  <joseplluis@lliuretic.cat>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/takepos/receipt.php
 *	\ingroup    takepos
 *	\brief      Page to show a receipt.
 */

if (!isset($action)) {
	//if (! defined('NOREQUIREUSER'))	define('NOREQUIREUSER', '1');	// Not disabled cause need to load personalized language
	//if (! defined('NOREQUIREDB'))		define('NOREQUIREDB', '1');		// Not disabled cause need to load personalized language
	//if (! defined('NOREQUIRESOC'))		define('NOREQUIRESOC', '1');
	//if (! defined('NOREQUIRETRAN'))		define('NOREQUIRETRAN', '1');
	if (!defined('NOTOKENRENEWAL'))	define('NOTOKENRENEWAL', '1');
	if (!defined('NOREQUIREMENU'))	define('NOREQUIREMENU', '1');
	if (!defined('NOREQUIREHTML'))	define('NOREQUIREHTML', '1');
	if (!defined('NOREQUIREAJAX'))	define('NOREQUIREAJAX', '1');

	require '../main.inc.php'; // If this file is called from send.php avoid load again
}
include_once DOL_DOCUMENT_ROOT.'/compta/facture/class/facture.class.php';

$langs->loadLangs(array("main", "cashdesk", "companies"));

$place = (GETPOST('place', 'aZ09') ? GETPOST('place', 'aZ09') : 0); // $place is id of table for Ba or Restaurant

$facid = GETPOST('facid', 'int');

$gift = GETPOST('gift', 'int');
$cantidad=0;
if (empty($user->rights->takepos->run)) {
	accessforbidden();
}


/*
 * View
 */

top_httphead('text/html');

if ($place > 0)
{
	$sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."facture where ref='(PROV-POS".$_SESSION["takeposterminal"]."-".$place.")'";
	$resql = $db->query($sql);
	$obj = $db->fetch_object($resql);
	if ($obj)
	{
		$facid = $obj->rowid;
	}
}
$object = new Facture($db);
$object->fetch($facid);

// Call to external receipt modules if exist
$hookmanager->initHooks(array('takeposfrontend'), $facid);
$reshook = $hookmanager->executeHooks('TakeposReceipt', $parameters, $object);
if (!empty($hookmanager->resPrint)) {
	print $hookmanager->resPrint;
	exit;
}

// IMPORTANT: This file is sended to 'Takepos Printing' application. Keep basic file. No external files as css, js... If you need images use absolute path.
?>
<html>
<body>
<div class="figure">
<img src="img/OHC-Beef-Logo-Square_mini.png"  alt="OHC Logo" width="70" height="60">
</div>
<style>
.right {
    text-align: right;
}
.center {
    text-align: center;
}
.left {
    text-align: left;
}
</style>
<center>
<font size="4">
<?php echo '<b>'.$mysoc->name.'</b>'; ?>
</font>
</center>
<br>
<p class="left">
<?php
$constFreeText = 'TAKEPOS_HEADER'.$_SESSION['takeposterminal'];
if (!empty($conf->global->TAKEPOS_HEADER) || !empty($conf->global->{$constFreeText}))
{
	$newfreetext = '';
	$substitutionarray = getCommonSubstitutionArray($langs);
	if (!empty($conf->global->TAKEPOS_HEADER))      $newfreetext .= make_substitutions($conf->global->TAKEPOS_HEADER, $substitutionarray);
	if (!empty($conf->global->{$constFreeText}))    $newfreetext .= make_substitutions($conf->global->{$constFreeText}, $substitutionarray);
	print $newfreetext;
}
?>
</p>
<p class="right">
<?php
print $langs->trans('Date')." ".dol_print_date($object->date, 'day').'<br>';
if (!empty($conf->global->TAKEPOS_RECEIPT_NAME)) print $conf->global->TAKEPOS_RECEIPT_NAME." ";
if ($object->statut == Facture::STATUS_DRAFT) print str_replace(")", "", str_replace("-", " ".$langs->trans('Place')." ", str_replace("(PROV-POS", $langs->trans("Terminal")." ", $object->ref)));
else print $object->ref;
if ($conf->global->TAKEPOS_SHOW_CUSTOMER)
{
	if ($object->socid != $conf->global->{'CASHDESK_ID_THIRDPARTY'.$_SESSION["takeposterminal"]})
	{
		$soc = new Societe($db);
		if ($object->socid > 0) $soc->fetch($object->socid);
		else $soc->fetch($conf->global->{'CASHDESK_ID_THIRDPARTY'.$_SESSION["takeposterminal"]});
		print "<br>".$langs->trans("Customer").': '.$soc->name;
	}
}
?>
</p>
<br>


<table width="100%" style="border-top-style: double;">
    <thead>
	<tr>
        <th class="center"><?php print $langs->trans("Label"); ?></th>
        <th class="right"><?php print $langs->trans("Qty"); ?></th>
         <th class="center"><?php print $langs->trans("Discounts"); ?></th>
        <th class="right"><?php if ($gift != 1) print $langs->trans("Price"); ?></th>
        <th class="right"><?php if ($gift != 1) print $langs->trans("TotalTTC"); ?></th>
	</tr>
    </thead>
    <tbody>
    <?php
	foreach ($object->lines as $line)
	{
        $cantidad+=$line->qty;
		?>
    <tr>
        <td>
		<?php if (!empty($line->product_label)) echo $line->product_label;
		else echo $line->description; ?>
        </td>
        <td class="right"><?php echo $line->qty; ?></td>
        <td class="center"><?php echo $line->remise_percent."%"; ?></td>
        <td class="right"><?php if ($gift != 1) echo price(price2num($line->total_ttc / $line->qty, 'MT'), 1); ?></td>
        <td class="right"><?php if ($gift != 1) echo price($line->total_ttc, 1); ?></td>
    </tr>
        <?php
	}
	?>
    </tbody>
</table>
<br>
<table class="right">

<?php if ($conf->global->TAKEPOS_TICKET_VAT_GROUPPED) {
	$vat_groups = array();

	foreach ($object->lines as $line)
	{
		if (!array_key_exists($line->tva_tx, $vat_groups)) {
			$vat_groups[$line->tva_tx] = 0;
		}
		$vat_groups[$line->tva_tx] += $line->total_tva;
	}
	foreach ($vat_groups as $key => $val) {
		?>
	<tr>
		<th align="right"><?php if ($gift != 1) echo $langs->trans("VAT").' '.vatrate($key, 1); ?></th>
		<td align="right"><?php if ($gift != 1) echo price($val, 1, '', 1, - 1, - 1, $conf->currency)."\n"; ?></td>
	</tr>
<tr>
    <th class="right"><?php if ($gift != 1) echo $langs->trans("Qty Total").'</th><td class="right">'.$cantidad."\n"; ?></td>
</tr>
        <?php
	}
} else { ?>
<?php
    //qty by beef
    $sql3= "SELECT SUM(fd.qty) AS tq, c.label AS cat from ".MAIN_DB_PREFIX."facturedet as fd
			LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = fd.fk_product
LEFT JOIN ".MAIN_DB_PREFIX."categorie_product AS cp ON fd.fk_product = cp.fk_product 
LEFT JOIN ".MAIN_DB_PREFIX."categorie AS c ON c.rowid = cp.fk_categorie 
WHERE  cp.fk_categorie IN (83,84,85,86,87,88,89,197,179) AND fk_facture = ".$facid ."
 " ;
    $resql3 = $db->query($sql3);
    foreach($resql3 as $value) {
        $tq = $value['tq'];
        $cat = $value['cat'];
    }
        ?>
    
    <?php
    //qty by cof
    $sql3= "SELECT SUM(fd.qty) AS tq2, c.label AS cat from ".MAIN_DB_PREFIX."facturedet as fd
			LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = fd.fk_product
LEFT JOIN ".MAIN_DB_PREFIX."categorie_product AS cp ON fd.fk_product = cp.fk_product 
LEFT JOIN ".MAIN_DB_PREFIX."categorie AS c ON c.rowid = cp.fk_categorie 
WHERE  cp.fk_categorie NOT IN (83,84,85,86,87,88,89,197,179, 197, 826) AND fk_facture =".$facid ."
 " ;
    $resql3 = $db->query($sql3);
    foreach($resql3 as $value) {
        $tq2 = $value['tq2'];
        $cat = $value['cat'];
    }
    echo '<p class="right">';
    if($tq <> 0){
    echo  'Qty Pounds '.$tq.'<br>';
    }
    if($tq2 <> 0)
    {
    echo  'Qty Each '.$tq2;
    }
    echo '</p>';
       ?>

<tr>
    <div class="right"><?php if ($gift != 1) echo $langs->trans("TotalHT").'  '.price($object->total_ht, 1, '', 1, - 1, - 1, $conf->currency); ?></div>
</tr>
<tr>
	<div class="right"><?php if ($gift != 1) echo $langs->trans("TotalVAT").'  '.price($object->total_tva, 1, '', 1, - 1, - 1, $conf->currency)."\n"; ?></div>
</tr>
<?php } ?>
<tr>
	<div class="right"><?php if ($gift != 1) echo ''.$langs->trans("TotalTTC").'  '.price($object->total_ttc, 1, '', 1, - 1, - 1, $conf->currency)."\n"; ?></div>
</tr>
    
<?php
if (!empty($conf->multicurrency->enabled) && $_SESSION["takeposcustomercurrency"] != "" && $conf->currency != $_SESSION["takeposcustomercurrency"]) {
	//Only show customer currency if multicurrency module is enabled, if currency selected and if this currency selected is not the same as main currency
	include_once DOL_DOCUMENT_ROOT.'/multicurrency/class/multicurrency.class.php';
	$multicurrency = new MultiCurrency($db);
	$multicurrency->fetch(0, $_SESSION["takeposcustomercurrency"]);
	echo '<tr><th class="right">';
	if ($gift != 1) echo ''.$langs->trans("TotalTTC").' '.$_SESSION["takeposcustomercurrency"].'</th><td class="right">'.price($object->total_ttc * $multicurrency->rate->rate, 1, '', 1, - 1, - 1, $_SESSION["takeposcustomercurrency"])."\n";
	echo '</td></tr>';
}

if ($conf->global->TAKEPOS_PRINT_PAYMENT_METHOD) {
	$sql = "SELECT p.pos_change as pos_change, p.datep as date, p.fk_paiement, p.num_paiement as num, pf.amount as amount, pf.multicurrency_amount,";
	$sql .= " cp.code";
	$sql .= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf, ".MAIN_DB_PREFIX."paiement as p";
	$sql .= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as cp ON p.fk_paiement = cp.id";
	$sql .= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = ".$facid;
	$sql .= " ORDER BY p.datep";
	$resql = $db->query($sql);
	if ($resql)
	{
		$num = $db->num_rows($resql);
		$i = 0;
		while ($i < $num) {
			$row = $db->fetch_object($resql);
			echo '<tr>';
			echo '<div class="right">';
			$amount_payment = (!empty($conf->multicurrency->enabled) && $object->multicurrency_tx != 1) ? $row->multicurrency_amount : $row->amount;
			if ($row->code == "LIQ") $amount_payment = $amount_payment + $row->pos_change; // Show amount with excess received if is cash payment
			echo $langs->transnoentitiesnoconv("PaymentTypeShort".$row->code).'  '.price($amount_payment, 1, '', 1, - 1, - 1, $conf->currency);
		echo '</div>';
				echo '</tr>';
		//extrafield transaction no.
			$sql2= "select fex.cardnumber AS cdn from llx_facture AS f 
			LEFT JOIN ".MAIN_DB_PREFIX."facture_extrafields AS fex ON f.rowid = fex.fk_object 
			WHERE f.rowid = ".$facid ;
			$resql2 = $db->query($sql2);
			foreach($resql2 as $value) {
          $ba2 = $value['cdn'];
			}
			if($ba2 <> 0)
			{
				echo '<div class="right">';
				echo 'CC Trans  '.$ba2;
				echo '</div>';
				echo '</tr>';
			}
				

			
            /* lines for qty cat
  			$num = $this->db->num_rows($resql3);

            $b=0;
            foreach($resql3 as $values1)
            {
                foreach($values1 as $value1)
                {
                    $cat[$b][].=	$value1;
                }
                $b=$b+1;
            }
            for ($i=0; $i < $num; $i++)
            {
				echo '<td class="right">';
				echo  $cat[$i][0].' :';
				echo '</td>';
				echo '<td class="right">';
				echo  $cat[$i][1];
				echo '</td>';
				echo '</tr>';
            }*/
        

      

			echo '</td>';
			echo '</tr>';
			if ($row->code == "LIQ" && $row->pos_change > 0) // Print change only in cash payments
			{
				echo '<tr>';
				echo '<div class="right">';
				echo $langs->trans("Change")." ".price($row->pos_change, 1, '', 1, - 1, - 1, $conf->currency);
				echo '</div>';
				//	echo '<div class="right">';
			//	echo price($row->pos_change, 1, '', 1, - 1, - 1, $conf->currency);
				//echo '</div>';
				echo '</tr>';
         //   print '<img src="home/oregonhyadmin/public_html/orhydev/htdocs/doc/images/thankyou.png" style="float:right;">';

			}
			$i++;
		}
  
	}
}
?>
</table>
<div style="border-top-style: double;">
<br>
<br>
<br>
<center>
<font size="4">
<?php echo 'Thanks for supporting local!' ?>
</font>
</center>
<?php
$constFreeText = 'TAKEPOS_FOOTER'.$_SESSION['takeposterminal'];
if (!empty($conf->global->TAKEPOS_FOOTER) || !empty($conf->global->{$constFreeText}))
{
	$newfreetext = '';
	$substitutionarray = getCommonSubstitutionArray($langs);
	if (!empty($conf->global->{$constFreeText}))    $newfreetext .= make_substitutions($conf->global->{$constFreeText}, $substitutionarray);
	if (!empty($conf->global->TAKEPOS_FOOTER))      $newfreetext .= make_substitutions($conf->global->TAKEPOS_FOOTER, $substitutionarray);
	print $newfreetext;
}
?>

<script type="text/javascript">
    window.print();
</script>
</body>
</html>
