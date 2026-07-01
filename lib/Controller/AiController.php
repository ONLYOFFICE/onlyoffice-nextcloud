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

namespace OCA\Onlyoffice\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDisplayResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\Response;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\IRequest;
use OCP\TaskProcessing\IManager as ITaskProcessingManager;
use OCP\TaskProcessing\Task;
use Psr\Log\LoggerInterface;

/**
 * Adapter between the ONLYOFFICE editor's AI plugin and Nextcloud TaskProcessing.
 */
class AiController extends Controller {

    private const PROVIDER_NAME = "Nextcloud";

    private const MODEL_NEXTCLOUD = "nextcloud-ai";

    /**
     * OnlyOffice editor capability bit => the Nextcloud task type
     */
    private const CAPABILITY_TASK_TYPES = [
        1 => "core:text2text:chat",
        2 => "core:text2image",
    ];

    public function __construct(
        string $appName,
        IRequest $request,
        private readonly ITaskProcessingManager $taskProcessing,
        private readonly IRootFolder $rootFolder,
        private readonly LoggerInterface $logger,
        private readonly ?string $userId
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * Schedule an OpenAI-style request as a TaskProcessing task and return its id.
     *
     * @param string $path the OpenAI path captured after /ai/
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function proxy(string $path): Response {
        $model = (string)$this->request->getParam("model", "");
        if ($model !== self::MODEL_NEXTCLOUD) {
            return new JSONResponse(
                ["error" => ["message" => "Unknown model: " . $model]],
                Http::STATUS_BAD_REQUEST
            );
        }

        try {
            $task = $this->buildTask($path);
        } catch (\InvalidArgumentException $e) {
            return new JSONResponse(
                ["error" => ["message" => $e->getMessage()]],
                Http::STATUS_NOT_FOUND
            );
        }

        try {
            $this->taskProcessing->scheduleTask($task);
        } catch (\Throwable $e) {
            $this->logger->error("AI schedule failed: " . $e->getMessage(), ["app" => "onlyoffice"]);
            return new JSONResponse(
                ["error" => ["message" => $e->getMessage()]],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        return new JSONResponse(["id" => $task->getId()]);
    }

    /**
     * Poll a scheduled task.
     *
     * @param int $id the scheduled task id
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function task(int $id): Response {
        try {
            $task = $this->taskProcessing->getUserTask($id, $this->userId);
        } catch (\Throwable $e) {
            return new JSONResponse(["error" => ["message" => "Task not found"]], Http::STATUS_NOT_FOUND);
        }

        if ($task->getAppId() !== $this->appName) {
            return new JSONResponse(["error" => ["message" => "Task not found"]], Http::STATUS_NOT_FOUND);
        }

        $status = $task->getStatus();

        if ($status === Task::STATUS_SUCCESSFUL) {
            $stream = filter_var($this->request->getParam("stream", false), FILTER_VALIDATE_BOOLEAN);
            return $this->formatResult($task, $stream);
        }

        if (in_array($status, [Task::STATUS_SCHEDULED, Task::STATUS_RUNNING, Task::STATUS_UNKNOWN], true)) {
            $started = $status === Task::STATUS_RUNNING || $task->getStartedAt() !== null;
            return new JSONResponse(["status" => $started ? "running" : "scheduled"], Http::STATUS_ACCEPTED);
        }

        return new JSONResponse(
            ["error" => ["message" => $task->getErrorMessage() ?? "Task failed"]],
            Http::STATUS_INTERNAL_SERVER_ERROR
        );
    }

    /**
     * Cancel a scheduled task.
     *
     * @param int $id the scheduled task id
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function cancelTask(int $id): JSONResponse {
        try {
            $task = $this->taskProcessing->getUserTask($id, $this->userId);
            if ($task->getAppId() !== $this->appName) {
                return new JSONResponse(["error" => ["message" => "Task not found"]], Http::STATUS_NOT_FOUND);
            }
            $this->taskProcessing->cancelTask($id);
        } catch (\Throwable $e) {
            return new JSONResponse(["error" => ["message" => $e->getMessage()]], Http::STATUS_INTERNAL_SERVER_ERROR);
        }
        return new JSONResponse(["status" => "cancelled"]);
    }

    /**
     * Build the TaskProcessing task for an OpenAI path.
     *
     * @throws \InvalidArgumentException for an unsupported path
     */
    private function buildTask(string $path): Task {
        if (str_ends_with($path, "chat/completions")) {
            $messages = $this->request->getParam("messages", []);
            [$systemPrompt, $input, $history] = $this->splitMessages(is_array($messages) ? $messages : []);
            return new Task(
                "core:text2text:chat",
                ["system_prompt" => $systemPrompt, "input" => $input, "history" => $history],
                $this->appName,
                $this->userId
            );
        }
        if (str_ends_with($path, "images/generations")) {
            $prompt = (string)$this->request->getParam("prompt", "");
            $count = max(1, (int)$this->request->getParam("n", 1));
            $input = ["input" => $prompt, "numberOfImages" => $count];
            $size = $this->imageSize();
            if ($size !== null) {
                $input["size"] = $size;
            }
            return new Task("core:text2image", $input, $this->appName, $this->userId);
        }
        throw new \InvalidArgumentException("Unsupported path: " . $path);
    }

    /**
     * Return image size.
     */
    private function imageSize(): ?string {
        $width = (int)$this->request->getParam("width", 0);
        $height = (int)$this->request->getParam("height", 0);
        if ($width > 0 && $height > 0) {
            return $width . "x" . $height;
        }
        $size = $this->request->getParam("size");
        return is_string($size) && $size !== "" ? $size : null;
    }

    /**
     * Turn a successful task into its OpenAI response shape.
     */
    private function formatResult(Task $task, bool $stream): Response {
        if ($task->getTaskTypeId() === "core:text2image") {
            $data = [];
            foreach ($this->taskProcessing->extractFileIdsFromTask($task) as $fileId) {
                $bytes = $this->readOutputFile((int)$fileId);
                if ($bytes !== null) {
                    $data[] = ["b64_json" => base64_encode($bytes)];
                }
            }
            return new JSONResponse(["created" => time(), "data" => $data]);
        }

        $output = $task->getOutput();
        $text = is_array($output) ? (string)($output["output"] ?? "") : "";
        if (!$stream) {
            return new JSONResponse($this->chatCompletion($text));
        }

        $id = $this->chatId();
        $created = time();
        $body = $this->chatChunkFrame($id, $created, ["role" => "assistant", "content" => $text], null)
            . $this->chatChunkFrame($id, $created, [], "stop")
            . "data: [DONE]\n\n";

        return new DataDisplayResponse($body, Http::STATUS_OK, [
            "Content-Type" => "text/event-stream",
            "Cache-Control" => "no-cache, no-transform",
            "X-Accel-Buffering" => "no",
        ]);
    }

    /**
     * Return the provider name and the model(s) with a capability bitmask
     * derived from the Nextcloud task types currently available.
     */
    #[NoAdminRequired]
    #[NoCSRFRequired]
    public function config(): JSONResponse {
        try {
            $available = $this->taskProcessing->getAvailableTaskTypes(false, $this->userId);
        } catch (\Throwable $e) {
            $this->logger->error("AI config failed: " . $e->getMessage(), ["app" => "onlyoffice"]);
            $available = [];
        }

        $capabilities = 0;
        foreach (self::CAPABILITY_TASK_TYPES as $bit => $taskTypeId) {
            if (\array_key_exists($taskTypeId, $available)) {
                $capabilities |= $bit;
            }
        }

        $models = $capabilities === 0 ? [] : [[
            "id" => self::MODEL_NEXTCLOUD,
            "name" => self::PROVIDER_NAME . " [" . self::MODEL_NEXTCLOUD . "]",
            "capabilities" => $capabilities,
        ]];

        return new JSONResponse([
            "provider" => self::PROVIDER_NAME,
            "models" => $models,
        ]);
    }

    /**
     * Split OpenAI chat messages into the core:text2text:chat input shape.
     * The last turn becomes the prompt; earlier turns become history.
     *
     * @param array<int, array<string, mixed>> $messages
     * @return array{0: string, 1: string, 2: list<string>} [systemPrompt, input, history]
     */
    private function splitMessages(array $messages): array {
        $systemPrompt = "";
        $turns = [];
        foreach ($messages as $message) {
            $content = $this->flattenContent($message["content"] ?? "");
            if (($message["role"] ?? "user") === "system") {
                $systemPrompt = $systemPrompt === "" ? $content : $systemPrompt . "\n" . $content;
                continue;
            }
            $turns[] = [
                "role" => ($message["role"] ?? "user") === "assistant" ? "assistant" : "user",
                "content" => $content,
            ];
        }

        $input = "";
        if (!empty($turns)) {
            $input = (string)array_pop($turns)["content"];
        }

        $history = array_map(static fn (array $turn): string => json_encode($turn), $turns);

        return [$systemPrompt, $input, array_values($history)];
    }

    /**
     * OpenAI message content can be a string or an array of typed parts.
     */
    private function flattenContent(mixed $content): string {
        if (is_string($content)) {
            return $content;
        }
        if (is_array($content)) {
            $text = "";
            foreach ($content as $part) {
                if (is_string($part)) {
                    $text .= $part;
                } elseif (is_array($part) && isset($part["text"]) && is_string($part["text"])) {
                    $text .= $part["text"];
                }
            }
            return $text;
        }
        return "";
    }

    /**
     * @return array<string, mixed>
     */
    private function chatCompletion(string $text): array {
        return [
            "id" => $this->chatId(),
            "object" => "chat.completion",
            "created" => time(),
            "model" => self::MODEL_NEXTCLOUD,
            "choices" => [[
                "index" => 0,
                "message" => ["role" => "assistant", "content" => $text],
                "finish_reason" => "stop",
            ]],
            "usage" => ["prompt_tokens" => 0, "completion_tokens" => 0, "total_tokens" => 0],
        ];
    }

    /**
     * @param array<string, mixed> $delta
     */
    private function chatChunkFrame(string $id, int $created, array $delta, ?string $finishReason): string {
        $frame = [
            "id" => $id,
            "object" => "chat.completion.chunk",
            "created" => $created,
            "model" => self::MODEL_NEXTCLOUD,
            "choices" => [[
                "index" => 0,
                "delta" => (object)$delta,
                "finish_reason" => $finishReason,
            ]],
        ];
        return "data: " . json_encode($frame) . "\n\n";
    }

    private function chatId(): string {
        return "chatcmpl-" . bin2hex(random_bytes(12));
    }

    /**
     * Read a TaskProcessing output file by its id.
     */
    private function readOutputFile(int $fileId): ?string {
        $node = $this->rootFolder->getFirstNodeById($fileId);
        if (!$node instanceof File) {
            $node = $this->rootFolder->getFirstNodeByIdInPath(
                $fileId,
                "/" . $this->rootFolder->getAppDataDirectoryName() . "/"
            );
        }
        return $node instanceof File ? $node->getContent() : null;
    }
}
