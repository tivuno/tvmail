<?php
/**
 * E-mail SMTP PrestaShop module ”Samos”
 *
 * @author    tivuno.com
 * @copyright 2018 - 2023 © tivuno.com
 * @license   Basic license | One license per (sub)domain
 */

class Tvmail extends Module
{
    public function __construct()
    {
        $this->name = 'tvmail';
        $this->tab = 'emailing';
        $this->version = '1.0.0';
        $this->author = 'tivuno.com';
        $this->ps_versions_compliancy = ['min' => '1.7.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;
        $this->displayName = Context::getContext()->getTranslator()->trans(
            'E-mail SMTP PrestaShop module ”Samos”',
            [],
            'Modules.Tvmail.Admin'
        );
        $this->description = Context::getContext()->getTranslator()->trans(
            'Send mails even from localhost. No more lost sales.',
            [],
            'Modules.Tvmail.Admin'
        );
        parent::__construct();
    }
}
