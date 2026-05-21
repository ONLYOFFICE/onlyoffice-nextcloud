<?php
/*
 * Copyright (C) Ascensio System SIA, 2009-2026
 *
 * This program is a free software product. You can redistribute it and/or
 * modify it under the terms of the GNU Affero General Public License (AGPL)
 * version 3 as published by the Free Software Foundation, together with the
 * additional terms provided in the LICENSE file.
 *
 * This program is distributed WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. For
 * details, see the GNU AGPL at: https://www.gnu.org/licenses/agpl-3.0.html
 *
 * You can contact Ascensio System SIA by email at info@onlyoffice.com
 * or by postal mail at 20A-6 Ernesta Birznieka-Upisha Street, Riga,
 * LV-1050, Latvia, European Union.
 *
 * The interactive user interfaces in modified versions of the Program
 * are required to display Appropriate Legal Notices in accordance with
 * Section 5 of the GNU AGPL version 3.
 *
 * No trademark rights are granted under this License.
 *
 * All non-code elements of the Product, including illustrations,
 * icon sets, and technical writing content, are licensed under the
 * Creative Commons Attribution-ShareAlike 4.0 International License:
 * https://creativecommons.org/licenses/by-sa/4.0/legalcode
 *
 * This license applies only to such non-code elements and does not
 * modify or replace the licensing terms applicable to the Program's
 * source code, which remains licensed under the GNU Affero General
 * Public License v3.
 *
 * SPDX-License-Identifier: AGPL-3.0-only
 */

namespace OCA\Onlyoffice;

use OCP\Mail\IEMailTemplate;
use OCP\Mail\IMailer;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\Mail\Provider\IManager;

/**
 * Email manager
 *
 * @package OCA\Onlyoffice
 */
class EmailManager {

    public function __construct(
        private readonly string $appName,
        private readonly IL10N $trans,
        private readonly LoggerInterface $logger,
        private readonly IMailer $mailer,
        private readonly IUserManager $userManager,
        private readonly IURLGenerator $urlGenerator,
        private readonly IManager $mailManager,
    ) {}

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
    ): bool {
        $recipient = $this->userManager->get($recipientId);
        if (empty($recipient)) {
            $this->logger->error("recipient $recipientId is null");
            return false;
        }
        $email = $recipient->getEMailAddress();
        if (empty($email)) {
            $this->logger->info("The mentioned recipient $recipientId does not have an email");
            return false;
        }
        $recipientName = $recipient->getDisplayName();

        $notifier = $this->userManager->get($notifierId);
        if (empty($notifier)) {
            $this->logger->error("notifier $notifierId is null");
            return false;
        }
        $notifierName = $notifier->getDisplayName();

        $editorLink = $this->urlGenerator->linkToRouteAbsolute($this->appName . ".editor.index", [
            "fileId" => $fileId,
            "anchor" => $anchor
        ]);
        $notifierLink = $this->urlGenerator->linkToRouteAbsolute('core.ProfilePage.index', ['targetUserId' => $notifierId]);
        $subject = $this->trans->t("You were mentioned in the document");
        $heading = $this->trans->t("%1\$s mentioned you in the document comment", [$notifierName]);
        $bodyHtml = $this->trans->t(
            "This is a mail message to notify that you have been mentioned by <a href=\"%1\$s\">%2\$s</a> in the comment to the <a href=\"%3\$s\">%4\$s</a>:<br>\"%5\$s\"",
            [$notifierLink, $notifierName, $editorLink, $fileName, $notificationObjectId]
        );
        $this->logger->debug($bodyHtml);
        $button = [$this->trans->t("Open file"), $editorLink];
        $template = $this->buildEmailTemplate($subject, $heading, $bodyHtml, $button);

        $result = $this->sendEmailNotification($template, $email, $recipientName);
        if ($result) {
            $this->logger->info("Email to $recipientId was sent");
        }
        return $result;
    }

    /**
     * Send notification about editors unsuccessfull check via email
     *
     * @param string $uid - user id
     *
     * @return bool
     */
    public function notifyEditorsCheckEmail(string $uid): bool {
        $user = $this->userManager->get($uid);
        if (empty($user)) {
            $this->logger->error("recipient $uid is null");
            return false;
        }
        $email = $user->getEMailAddress();
        if (empty($email)) {
            $this->logger->info("The notification recipient $uid does not have an email");
            return false;
        }
        $userName = $user->getDisplayName();

        $subject = $this->trans->t("ONLYOFFICE Document Server is unavailable");
        $bodyHtml = $this->trans->t("This is a mail message to notify that the connection with the ONLYOFFICE Document Server has been lost. Please check the connection settings:");
        $appSettingsLink = $this->urlGenerator->getAbsoluteURL("/settings/admin/".$this->appName);
        $button = [$this->trans->t("Go to Settings"), $appSettingsLink];
        $template = $this->buildEmailTemplate($subject, $subject, $bodyHtml, $button);

        $result = $this->sendEmailNotification($template, $email, $userName);
        if ($result) {
            $this->logger->info("Email to $uid was sent");
        }
        return $result;
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
    private function buildEmailTemplate(
        string $subject,
        string $heading,
        string $body,
        array $button = []
    ): IEMailTemplate {
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
     */
    private function sendEmailNotification(IEMailTemplate $template, string $email, string $recipientName): bool {
        try {
            $message = $this->mailer->createMessage();
            $message->setTo([$email => $recipientName]);
            $message->useTemplate($template);
            $errors = $this->mailer->send($message);

            if (!empty($errors)) {
                $this->logger->error("Email service error: " . json_encode($errors));
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error("Send email", ['exception' => $e]);
            return false;
        }

        return true;
    }

    /**
     * Get user emails list that can send email messages
     * @param string $uid
     * @return array
     */
    public function getSenderAddressesFor(string $uid): array {
        $emails = [];

        $mailProviders = $this->mailManager->services($uid);

        foreach ($mailProviders as $mailServices) {
            $serviceEmails = array_filter(array_map(function ($service) {
                return $service->capable('MessageSend') ? $service->getPrimaryAddress()->getAddress(): null;
            }, $mailServices));
            array_push($emails, ...$serviceEmails);
        }

        return $emails;
    }
}
