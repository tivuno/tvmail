<?php
/**
 * E-mail SMTP PrestaShop module - Samos
 * @author    tivuno.com <hi@tivuno.com>
 * @copyright 2018 - 2025 © tivuno.com
 * @license   https://tivuno.com/blog/nea-tis-epicheirisis/apli-adeia
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}
class Tvmail extends Module
{
    public function __construct()
    {
        $this->name = 'tvmail';
        $this->tab = 'emailing';
        $this->version = '1.0.1';
        $this->author = 'tivuno.com';
        $this->ps_versions_compliancy = [
            'min' => '1.7.0', 'max' => _PS_VERSION_,
        ];
        $this->bootstrap = true;
        $this->displayName = $this->l('E-mail SMTP PrestaShop module - Samos');
        $this->description = $this->l('Send mails even from localhost. No more lost sales.');
        parent::__construct();
    }
}
