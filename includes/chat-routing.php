<?php
declare(strict_types=1);

/** Parse the model's semantic routing marker without trusting it as application state. */
function cloudsys_parse_chat_completion(string $completion): array
{
    $completion = trim($completion);
    if (preg_match('/^\s*(?:\*\*)?SCOPE:\s*(IN|OUT|HANDOFF)(?:\*\*)?\s*\R+(.*)$/is', $completion, $match) !== 1) {
        return ['out', 'I can help with general NetSuite, ERP, business-process automation, and AI-agent questions. What would you like to understand in one of those areas?'];
    }
    $scope = strtolower($match[1]);
    $reply = trim($match[2]);
    if ($reply === '') $reply = $scope === 'out'
        ? 'That is outside the topics I can help with. Ask me about NetSuite, ERP workflows, automation, or business AI.'
        : 'Could you rephrase that question?';
    return [$scope, $reply];
}
