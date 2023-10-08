<?php
/**
 * Options for the fksdbexport plugin
 *
 * @author Michal Koutný <michal@fykos.cz>
 */
$meta['expiration'] = ['numeric'];
$meta['contest'] = ['multichoice', '_choices' => ['fykos', 'vyfuk']];
$meta['wsdl'] = ['string'];
$meta['fksdb_login'] = ['string'];
$meta['fksdb_password'] = ['password'];
$meta['fksdb_json_api'] = ['string'];
$meta['tmp_dir'] = ['string'];
