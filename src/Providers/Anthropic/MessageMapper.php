<?php

namespace NeuronAI\Providers\Anthropic;

use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\ToolCallMessage;
use NeuronAI\Chat\Messages\ToolCallResultMessage;
use NeuronAI\Chat\Messages\UserMessage;
use NeuronAI\Exceptions\AgentException;
use NeuronAI\Providers\MessageMapperInterface;
use NeuronAI\Tools\ToolInterface;

class MessageMapper implements MessageMapperInterface
{
    protected array $mapping = [];

    public function map(array $messages): array
    {
        foreach ($messages as $message) {
            match ($message::class) {
                Message::class,
                UserMessage::class,
                AssistantMessage::class => $this->mapMessage($message),
                ToolCallMessage::class => $this->mapToolCall($message),
                ToolCallResultMessage::class => $this->mapToolsResult($message),
                default => throw new AgentException('Could not map message type '.$message::class),
            };
        }

        return $this->mapping;
    }

    protected function mapMessage(Message $message): void
    {
        $message = $message->jsonSerialize();

        if (\array_key_exists('usage', $message)) {
            unset($message['usage']);
        }

        $this->mapping[] = $message;
    }

    protected function mapToolCall(ToolCallMessage $message): void
    {
        $message = $message->jsonSerialize();

        if (\array_key_exists('usage', $message)) {
            unset($message['usage']);
        }

        unset($message['type']);
        unset($message['tools']);

        $this->mapping[] = $message;
    }

    protected function mapToolsResult(ToolCallResultMessage $message): void
    {
        $this->mapping[] = [
            'role' => Message::ROLE_USER,
            'content' => \array_map(function (ToolInterface $tool) {
                return [
                    'type' => 'tool_result',
                    'tool_use_id' => $tool->getCallId(),
                    'content' => $tool->getResult(),
                ];
            }, $message->getTools())
        ];
    }
}
