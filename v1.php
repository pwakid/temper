<?php

class TemplateEngine
{
    private $variables = []; // Stores variables for the template

    // Assign variables to the template
    public function assign($key, $value)
    {
        $this->variables[$key] = $value;
    }

    // Render the template
    public function render($template)
    {
        // Load template file
        $templateFile = __DIR__ . "/templates/{$template}.tpl";
        if (!file_exists($templateFile)) {
            throw new Exception("Template file '{$template}' not found!");
        }

        // Get the template content
        $content = file_get_contents($templateFile);

        // Process partials (includes)
        $content = $this->processPartials($content);

        // Replace variables
        $content = $this->replaceVariables($content);

        // Process loops
        $content = $this->processLoops($content);

        // Process conditions
        $content = $this->processConditions($content);

        return $content;
    }

    // Process partials (includes)
    private function processPartials($content)
    {
        return preg_replace_callback('/{{\s*include\((.+?)\)\s*}}/', function ($matches) {
            $includeSyntax = $matches[1];
            $parts = explode(',', $includeSyntax);

            // Extract partial name
            $partialName = trim(array_shift($parts), '\"\'');
            $partialFile = __DIR__ . '/templates/partials/' . $partialName . '.tpl';

            if (!file_exists($partialFile)) {
                return "<!-- Partial '{$partialName}' not found -->";
            }

            // Extract variables passed to the partial
            $localVariables = [];
            foreach ($parts as $pair) {
                [$key, $value] = explode('=', $pair);
                $localVariables[trim($key)] = trim($value, '\"\'');
            }

            // Get the partial content
            $partialContent = file_get_contents($partialFile);

            // Process includes within the partial (nested partials)
            $partialContent = $this->processPartials($partialContent);

            // Merge local variables with global ones for this partial
            $mergedVariables = array_merge($this->variables, $localVariables);

            // Replace variables in the partial using merged variables
            foreach ($mergedVariables as $key => $value) {
                $partialContent = str_replace("{{ $key }}", htmlspecialchars($value), $partialContent);
            }

            return $partialContent;
        }, $content);
    }

    // Replace variables
    private function replaceVariables($content)
    {
        foreach ($this->variables as $key => $value) {
            $content = str_replace("{{ $key }}", htmlspecialchars($value), $content);
        }
        return $content;
    }

    // Process loops (foreach)
    private function processLoops($content)
    {
        return preg_replace_callback('/{{\s*foreach\s+(\$[\w]+)\s+as\s+\$([\w]+)\s*}}(.*?){{\s*endforeach\s*}}/s', function ($matches) {
            $arrayName = $matches[1];
            $itemName = $matches[2];
            $block = $matches[3];

            if (!isset($this->variables[substr($arrayName, 1)])) {
                return '';
            }

            $result = '';
            foreach ($this->variables[substr($arrayName, 1)] as $item) {
                $blockWithItem = str_replace("{{ $itemName }}", htmlspecialchars($item), $block);
                $result .= $blockWithItem;
            }
            return $result;
        }, $content);
    }

    // Process conditions (if)
    private function processConditions($content)
    {
        return preg_replace_callback('/{{\s*if\s+\$(.+?)\s*}}(.*?){{\s*endif\s*}}/s', function ($matches) {
            $variable = $matches[1];
            $block = $matches[2];

            return !empty($this->variables[$variable]) ? $block : '';
        }, $content);
    }
}

?>
