<?php
/**
 * E-mail SMTP PrestaShop module ”Samos”
 *
 * @author    tivuno.com
 * @copyright 2018 - 2023 © tivuno.com
 * @license   Basic license | One license per (sub)domain
 */

use Exception;
use PHPMailer\PHPMailer\PHPMailer;

class Mail extends MailCore
{
    /**
     * It sends mail
     * @param $idLang
     * @param $template
     * @param $subject
     * @param $templateVars
     * @param $to
     * @param $toName
     * @param $from
     * @param $fromName
     * @param $fileAttachment
     * @param $mode_smtp
     * @param string $templatePath
     * @param bool $die
     * @param $idShop
     * @param $bcc
     * @param $replyTo
     * @param $replyToName
     * @return bool
     * @throws Exception
     * @throws PrestaShopException
     */
    public static function send(
        $idLang,
        $template,
        $subject,
        $templateVars,
        $to,
        $toName = null,
        $from = null,
        $fromName = null,
        $fileAttachment = null,
        $mode_smtp = null,
        $templatePath = _PS_MAIL_DIR_,
        $die = false,
        $idShop = null,
        $bcc = null,
        $replyTo = null,
        $replyToName = null
    ) {
        if (!$idShop) {
            $idShop = Context::getContext()->shop->id;
        }
        $hookBeforeEmailResult = Hook::exec(
            'actionEmailSendBefore',
            [
                'idLang' => &$idLang,
                'template' => &$template,
                'subject' => &$subject,
                'templateVars' => &$templateVars,
                'to' => &$to,
                'toName' => &$toName,
                'from' => &$from,
                'fromName' => &$fromName,
                'fileAttachment' => &$fileAttachment,
                'mode_smtp' => &$mode_smtp,
                'templatePath' => &$templatePath,
                'die' => &$die,
                'idShop' => &$idShop,
                'bcc' => &$bcc,
                'replyTo' => &$replyTo,
            ],
            null,
            true
        );

        if ($hookBeforeEmailResult === null) {
            $keepGoing = false;
        } else {
            $keepGoing = array_reduce(
                $hookBeforeEmailResult,
                function ($carry, $item) {
                    return ($item === false) ? false : $carry;
                },
                true
            );
        }

        if (!$keepGoing) {
            return true;
        }

        if (is_numeric($idShop) && $idShop) {
            $shop = new Shop((int) $idShop);
        }

        $configuration = Configuration::getMultiple(
            [
                'PS_SHOP_EMAIL',
                'PS_MAIL_METHOD',
                'PS_MAIL_SERVER',
                'PS_MAIL_USER',
                'PS_MAIL_PASSWD',
                'PS_SHOP_NAME',
                'PS_MAIL_SMTP_ENCRYPTION',
                'PS_MAIL_SMTP_PORT',
                'PS_MAIL_TYPE',
            ],
            null,
            null,
            $idShop
        );
        if ($configuration['PS_MAIL_METHOD'] == self::METHOD_DISABLE) {
            return true;
        }
        Hook::exec(
            'sendMailAlterTemplateVars',
            [
                'template' => $template,
                'template_vars' => &$templateVars,
            ]
        );

        if (
            !isset($configuration['PS_MAIL_SMTP_ENCRYPTION']) ||
            Tools::strtolower($configuration['PS_MAIL_SMTP_ENCRYPTION']) === 'off'
        ) {
            $configuration['PS_MAIL_SMTP_ENCRYPTION'] = false;
        }

        if (!isset($configuration['PS_MAIL_SMTP_PORT'])) {
            $configuration['PS_MAIL_SMTP_PORT'] = 'default';
        }

        if (!isset($from) || !Validate::isEmail($from)) {
            $from = $configuration['PS_SHOP_EMAIL'];
        }

        if (!Validate::isEmail($from)) {
            $from = null;
        }
        if (!isset($fromName) || !Validate::isMailName($fromName)) {
            $fromName = $configuration['PS_SHOP_NAME'];
        }

        if (!Validate::isMailName($fromName)) {
            $fromName = null;
        }

        if (!is_array($to) && !Validate::isEmail($to)) {
            parent::dieOrLog($die, 'Error: parameter "to" is corrupted');

            return false;
        }
        if (null !== $bcc && !is_array($bcc) && !Validate::isEmail($bcc)) {
            parent::dieOrLog($die, 'Error: parameter "bcc" is corrupted');
            $bcc = null;
        }

        if (!is_array($templateVars)) {
            $templateVars = [];
        }
        if (is_string($toName) && !empty($toName) && !Validate::isMailName($toName)) {
            $toName = null;
        }

        if (!Validate::isTplName($template)) {
            parent::dieOrLog($die, 'Error: invalid e-mail template');

            return false;
        }

        if (!Validate::isMailSubject($subject)) {
            parent::dieOrLog($die, 'Error: invalid e-mail subject');

            return false;
        }

        $message = new PHPMailer(true);
        $message->isSMTP();
        $message->Host = Configuration::get('PS_MAIL_SERVER');
        $message->SMTPAuth = true;
        $message->SMTPDebug = false;
        $message->Username = Configuration::get('PS_MAIL_USER');
        $message->Password = Configuration::get('PS_MAIL_PASSWD');
        $message->SMTPSecure = Configuration::get('PS_MAIL_SMTP_ENCRYPTION');
        $message->Port = Configuration::get('PS_MAIL_SMTP_PORT');
        $message->setFrom('hi@tivuno.com', 'Clients service');
        $message->isHTML(true);

        if (is_array($to)) {
            foreach ($to as $addr) {
                $addr = trim($addr);
                if (!Validate::isEmail($addr)) {
                    parent::dieOrLog($die, 'Error: invalid e-mail address');

                    return false;
                } else {
                    $message->addAddress($addr);
                }
            }
        } else {
            $addr = trim($to);
            if (!Validate::isEmail($addr)) {
                parent::dieOrLog($die, 'Error: invalid e-mail address');

                return false;
            } else {
                $message->addAddress($addr);
            }
        }

        try {
            $iso = Language::getIsoById((int) $idLang);
            $isoDefault = Language::getIsoById((int) Configuration::get('PS_LANG_DEFAULT'));
            $isoArray = [];
            if ($iso) {
                $isoArray[] = $iso;
            }

            if ($isoDefault && $iso !== $isoDefault) {
                $isoArray[] = $isoDefault;
            }

            if (!in_array('en', $isoArray)) {
                $isoArray[] = 'en';
            }

            $moduleName = false;
            if (
                isset($shop) &&
                preg_match(
                    '#' . $shop->physical_uri . 'modules/#',
                    str_replace(DIRECTORY_SEPARATOR, '/', $templatePath)
                ) &&
                preg_match('#modules/([a-z0-9_-]+)/#ui', str_replace(DIRECTORY_SEPARATOR, '/', $templatePath), $res)
            ) {
                $moduleName = $res[1];
            }

            if (isset($shop)) {
                $shop_theme = $shop->theme;
            } else {
                // Default theme
                $shop_theme = 'classic';
            }

            $isoTemplate = '';
            foreach ($isoArray as $isoCode) {
                $isoTemplate = $isoCode . '/' . $template;
                $templatePath = self::getTemplateBasePath($isoTemplate, $moduleName, $shop_theme);

                if (
                    !file_exists($templatePath . $isoTemplate . '.txt') &&
                    ($configuration['PS_MAIL_TYPE'] == Mail::TYPE_BOTH ||
                        $configuration['PS_MAIL_TYPE'] == Mail::TYPE_TEXT
                    )
                ) {
                    PrestaShopLogger::addLog(
                        Context::getContext()->getTranslator()->trans(
                            'Error - The following e-mail template is missing: %s',
                            [$templatePath . $isoTemplate . '.txt'],
                            'Admin.Advparameters.Notification'
                        )
                    );
                } elseif (
                    !file_exists($templatePath . $isoTemplate . '.html') &&
                    ($configuration['PS_MAIL_TYPE'] == Mail::TYPE_BOTH ||
                        $configuration['PS_MAIL_TYPE'] == Mail::TYPE_HTML
                    )
                ) {
                    PrestaShopLogger::addLog(
                        Context::getContext()->getTranslator()->trans(
                            'Error - The following e-mail template is missing: %s',
                            [$templatePath . $isoTemplate . '.html'],
                            'Admin.Advparameters.Notification'
                        )
                    );
                } else {
                    $templatePathExists = true;

                    break;
                }
            }

            if (empty($templatePathExists)) {
                parent::dieOrLog($die, 'Error - The following e-mail template is missing: %s', [$template]);

                return false;
            }

            $templateHtml = '';
            $templateTxt = '';
            Hook::exec(
                'actionEmailAddBeforeContent',
                [
                    'template' => $template,
                    'template_html' => &$templateHtml,
                    'template_txt' => &$templateTxt,
                    'id_lang' => (int) $idLang,
                ],
                null,
                true
            );
            $templateHtml .= Tools::file_get_contents($templatePath . $isoTemplate . '.html');
            $templateTxt .= strip_tags(
                html_entity_decode(
                    Tools::file_get_contents($templatePath . $isoTemplate . '.txt'),
                    null,
                    'utf-8'
                )
            );
            Hook::exec(
                'actionEmailAddAfterContent',
                [
                    'template' => $template,
                    'template_html' => &$templateHtml,
                    'template_txt' => &$templateTxt,
                    'id_lang' => (int) $idLang,
                ],
                null,
                true
            );

            $subject = '[' . strip_tags($configuration['PS_SHOP_NAME']) . '] ' . $subject;
            $message->Subject = $subject;

            if (!($replyTo && Validate::isEmail($replyTo))) {
                $replyTo = $from;
            }

            if (
                false !== Configuration::get('PS_LOGO_MAIL') &&
                file_exists(_PS_IMG_DIR_ . Configuration::get('PS_LOGO_MAIL', null, null, $idShop))
            ) {
                $logo = _PS_IMG_DIR_ . Configuration::get('PS_LOGO_MAIL', null, null, $idShop);
            } else {
                if (file_exists(_PS_IMG_DIR_ . Configuration::get('PS_LOGO', null, null, $idShop))) {
                    $logo = _PS_IMG_DIR_ . Configuration::get('PS_LOGO', null, null, $idShop);
                } else {
                    $templateVars['{shop_logo}'] = '';
                }
            }
            ShopUrl::cacheMainDomainForShop((int) $idShop);

            if (isset($logo)) {
                $templateVars['{shop_logo}'] = $message->AddEmbeddedImage($logo, 'mail_logo');
            }

            if ((Context::getContext()->link instanceof Link) === false) {
                Context::getContext()->link = new Link();
            }

            $templateVars['{shop_name}'] = Tools::safeOutput($configuration['PS_SHOP_NAME']);
            $templateVars['{shop_url}'] = Context::getContext()->link->getPageLink(
                'index',
                true,
                $idLang,
                null,
                false,
                $idShop
            );
            $templateVars['{my_account_url}'] = Context::getContext()->link->getPageLink(
                'my-account',
                true,
                $idLang,
                null,
                false,
                $idShop
            );
            $templateVars['{guest_tracking_url}'] = Context::getContext()->link->getPageLink(
                'guest-tracking',
                true,
                $idLang,
                null,
                false,
                $idShop
            );
            $templateVars['{history_url}'] = Context::getContext()->link->getPageLink(
                'history',
                true,
                $idLang,
                null,
                false,
                $idShop
            );
            $templateVars['{order_slip_url}'] = Context::getContext()->link->getPageLink(
                'order-slip',
                true,
                $idLang,
                null,
                false,
                $idShop
            );
            $templateVars['{color}'] = Tools::safeOutput(Configuration::get('PS_MAIL_COLOR', null, null, $idShop));
            $extraTemplateVars = [];
            Hook::exec(
                'actionGetExtraMailTemplateVars',
                [
                    'template' => $template,
                    'template_vars' => $templateVars,
                    'extra_template_vars' => &$extraTemplateVars,
                    'id_lang' => (int) $idLang,
                ],
                null,
                true
            );
            $templateVars = array_merge($templateVars, $extraTemplateVars);

            // Some strange issue
            unset($templateVars['{guest_tracking_url}']);

            $templateHtml = str_replace(
                array_keys($templateVars),
                array_values($templateVars),
                $templateHtml
            );

            $templateTxt = str_replace(
                array_keys($templateVars),
                array_values($templateVars),
                $templateTxt
            );

            if (
                $configuration['PS_MAIL_TYPE'] == Mail::TYPE_BOTH ||
                $configuration['PS_MAIL_TYPE'] == Mail::TYPE_TEXT
            ) {
                $message->AltBody = $templateTxt;
            }

            if (
                $configuration['PS_MAIL_TYPE'] == Mail::TYPE_BOTH ||
                $configuration['PS_MAIL_TYPE'] == Mail::TYPE_HTML
            ) {
                $message->Body = $templateHtml;
            }

            Hook::exec('actionMailAlterMessageBeforeSend', [
                'message' => &$message,
            ]);

            $send = $message->send();

            ShopUrl::resetMainDomainCache();

            if ($send && Configuration::get('PS_LOG_EMAILS')) {
                $mail = new Mail();
                $mail->template = Tools::substr($template, 0, 62);
                $mail->subject = Tools::substr($message->Subject, 0, 255);
                $mail->id_lang = (int) $idLang;
                $recipientsTo = $message->getAllRecipientAddresses();
                $recipientsCc = [];
                $recipientsBcc = [];
                if (!is_array($recipientsTo)) {
                    $recipientsTo = [];
                }
                if (!is_array($recipientsCc)) {
                    $recipientsCc = [];
                }
                if (!is_array($recipientsBcc)) {
                    $recipientsBcc = [];
                }
                foreach (array_merge($recipientsTo, $recipientsCc, $recipientsBcc) as $email => $recipient_name) {
                    $mail->id = null;
                    $mail->recipient = Tools::substr($email, 0, 255);
                    $mail->add();
                }
            }

            return $send;
        } catch (Exception $e) {
            PrestaShopLogger::addLog(
                'Error: ' . $e->getMessage(),
                3,
                null,
                'PHPMailer'
            );

            return false;
        }
    }
}
