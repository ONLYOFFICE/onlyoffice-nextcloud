<?php
/**
 *
 * (c) Copyright Ascensio System SIA 2024
 *
 * This program is a free software product.
 * You can redistribute it and/or modify it under the terms of the GNU Affero General Public License
 * (AGPL) version 3 as published by the Free Software Foundation.
 * In accordance with Section 7(a) of the GNU AGPL its Section 15 shall be amended to the effect
 * that Ascensio System SIA expressly excludes the warranty of non-infringement of any third-party rights.
 *
 * This program is distributed WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * For details, see the GNU AGPL at: http://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA at 20A-12 Ernesta Birznieka-Upisha street, Riga, Latvia, EU, LV-1050.
 *
 * The interactive user interfaces in modified source and object code versions of the Program
 * must display Appropriate Legal Notices, as required under Section 5 of the GNU AGPL version 3.
 *
 * Pursuant to Section 7(b) of the License you must retain the original Product logo when distributing the program.
 * Pursuant to Section 7(e) we decline to grant you any rights under trademark law for use of our trademarks.
 *
 * All the Product's GUI elements, including illustrations and icon sets, as well as technical
 * writing content are licensed under the terms of the Creative Commons Attribution-ShareAlike 4.0 International.
 * See the License terms at http://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 */

namespace OCA\Onlyoffice;

use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IURLGenerator;
use OCP\IUserManager;

/**
 * Template manager
 *
 * @package OCA\Onlyoffice
 */
class EmailManager {

    /**
     * Application name
     *
     * @var string
     */
    private $appName;

    /**
     * l10n service
     *
     * @var IL10N
     */
    private $trans;

    /**
     * Logger
     *
     * @var LoggerInterface
     */
    private $logger;

    /**
     * Mailer
     *
     * @var IMailer
     */
    private $mailer;

    /**
     * User manager
     *
     * @var IUserManager
     */
    private $userManager;

    /**
     * Url generator service
     *
     * @var IURLGenerator
     */
    private $urlGenerator;

    /**
     * @param string $appName - application name
     * @param IL10N $trans - l10n service
     * @param LoggerInterface $logger - logger
     * @param IMailer $mailer - mailer
     * @param IUserManager $userManager - user manager
     * @param IURLGenerator $urlGenerator - URL generator
     */
    public function __construct(
        $appName,
        IL10N $trans,
        LoggerInterface $logger,
        IMailer $mailer,
        IUserManager $userManager,
        IURLGenerator $urlGenerator,
    ) {
        $this->appName = $appName;
        $this->trans = $trans;
        $this->logger = $logger;
        $this->mailer = $mailer;
        $this->userManager = $userManager;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Send notification about mention via email
     *
     * @param string $notifierId - id of notifier user
     * @param string $recipientId - id of recipient user
     * @param string $fileId - file id
     * @param string $fileName - file name
     * @param string $anchor - anchor
     * @param string $notificationObjectId - object of notification
     * @return bool
     */
    public function notifyMentionEmail(
        string $notifierId,
        string $recipientId,
        string $fileId,
        string $fileName,
        string $anchor,
        string $notificationObjectId
    ) {
        $recipient = $this->userManager->get($recipientId);
        $email = $recipient->getEMailAddress();
        if (empty($email)) {
            $this->logger->info("Mention notification was not sent by e-mail");
            return false;
        }
        $notifier = $this->userManager->get($notifierId);
        $recipientName = $recipient->getDisplayName();
        $notifierName = $notifier->getDisplayName();
        $editorLink = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", [
            "fileId" => $fileId,
            "anchor" => $anchor
        ]);
        $notifierLink = $this->urlGenerator->linkToRouteAbsolute('core.ProfilePage.index', ['targetUserId' => $notifierId]);
        $subject = $this->trans->t("You were mentioned in the document");
        $this->logger->info($subject);
        $heading = $this->trans->t("%1\$s mentioned you in the document comment", [$notifierName]);
        $bodyHtml = $this->trans->t(
            "This is a mail message to notify that you have been mentioned by <a href=\"%1\$s\">%2\$s</a> in the comment to the <a href=\"%3\$s\">%4\$s</a>:<br>\"%5\$s\"",
            [$notifierLink, $notifierName, $editorLink, $fileName, $notificationObjectId]
        );
        $this->logger->info($bodyHtml);
        $button = [$this->trans->t("Open file"), $editorLink];
        $template = $this->buildEmailTemplate($subject, $heading, $bodyHtml, $button);
        return $this->sendEmailNotification($template, $email, $recipientName);
    }

    /**
     * Send notification about editors unsuccessfull check via email
     *
     * @param string $uid - user id
     *
     * @return bool
     */
    public function notifyEditorsCheckEmail(string $uid) {
        $user = $this->userManager->get($uid);
        $email = $user->getEMailAddress();
        if (empty($email)) {
            $this->logger->info("Editors check notification was not sent by e-mail");
            return false;
        }
        $userName = $user->getDisplayName();
        $subject = $this->trans->t("ONLYOFFICE Document Server is unavailable");
        $bodyHtml = $this->trans->t("This is a mail message to notify that the connection with the ONLYOFFICE Document Server has been lost. Please check the connection settings:");
        $appSettingsLink = $this->urlGenerator->getAbsoluteURL("/settings/admin/".$this->appName);
        $button = [$this->trans->t("Go to Settings"), $appSettingsLink];
        $template = $this->buildEmailTemplate($subject, $subject, $bodyHtml, $button);
        return $this->sendEmailNotification($template, $email, $userName);
    }

    /**
     * Build email template
     *
     * @param string $subject - e-mail subject text
     * @param string $heading - e-mail heading text
     * @param string $body - e-mail body html
     * @param array $button - params for NC-button (0-text, 1-link)
     *
     * @return IEMailTemplate
     */
    public function buildEmailTemplate(string $subject, string $heading, string $body, array $button = []) {
        $template = $this->mailer->createEMailTemplate("onlyoffice.NotifyEmail");
        $template->setSubject($subject);
        $template->addHeader();
        $template->addHeading($heading);
        $template->addBodyText($body, true);

        if (!empty($button) && isset($button[0]) && isset($button[1]) && is_string($button[0]) && is_string($button[1])) {
            $template->addBodyButton($button[0], $button[1]);
        }
        $template->addFooter();
        return $template;
    }

    /**
     * Send email
     *
     * @param IEMailTemplate $template - e-mail template
     * @param string $email - e-mail address
     * @param string $recipientName - recipient name
     *
     * @return bool
     */
    public function sendEmailNotification(IEMailTemplate $template, string $email, string $recipientName) {
        $message = $this->mailer->createMessage();
        $message->setTo([$email => $recipientName]);
        $message->useTemplate($template);
        $errors = $this->mailer->send($message);

        if (!empty($errors)) {
            $this->logger->debug("Email service error");
            return false;
        }

        return true;
    }
}
