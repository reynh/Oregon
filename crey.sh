mysql --user=aincodolibarr_dbAdmin --password='Q)$pkPaZ[pIT' --database= aincodolibarr_prod --execute="UPDATE saldo AS t1
SET t1.total = (SELECT  SUM((b.amount)/(mcr.rate)) AS total
FROM  llx_bank_account as ba
LEFT JOIN llx_bank as b ON ba.rowid = b.fk_account
LEFT JOIN llx_bank_account_extrafields AS bax ON  ba.rowid = bax.fk_object
LEFT JOIN llx_multicurrency AS mc ON ba.currency_code = mc.code
LEFT JOIN llx_multicurrency_rate AS mcr ON mc.rowid = mcr.fk_multicurrency
WHERE b.fk_account = ba.rowid AND ba.entity IN (1)
AND ba.rowid IN ('1', '5', '6', '8')
AND mcr.date_sync in (SELECT MAX(date_sync) from llx_multicurrency_rate GROUP BY fk_multicurrency))
WHERE t1.date_lim = CURDATE()"


