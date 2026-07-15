<?php

class Renderer
{
    /**
     * Replaces `{{ key }}` placeholders in `$template` with values from `$data`.
     *
     * @param array<string, string> $data
     */
    public function render(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', static function (array $match) use ($data): string {
            return $data[$match[1]];
        }, $template) ?? '';
    }
}
