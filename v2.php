<?php

class TemplateEngine
{
    private $variables = []; // Stores variables for the template
    private $templateDirs = []; // Stores the list of template directories

    // Constructor to set up template directories
    public function __construct($templateDirs = ['templates/default'])
    {
        $this->templateDirs = $templateDirs;
        $this->assignPostData(); // Auto-assign POST data to variables
    }

    // Assign variables to the template
    public function assign($key, $value)
    {
        $this->variables[$key] = $value;
    }

    // Auto-assign POST data to variables
    private function assignPostData()
    {
        foreach ($_POST as $key => $value) {
            $this->assign($key, $this->sanitize($value));
        }
    }

    // Sanitize input data
    private function sanitize($data)
    {
        if (is_array($data)) {
            return array_map([$this, 'sanitize'], $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }

    // Render the template
    public function render($template)
    {
        // Load template file from the first matching directory
        $templateFile = $this->findTemplateFile("{$template}.tpl");
        if (!$templateFile) {
            throw new Exception("Template file '{$template}' not found in any directory!");
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

    // Find a template file in the directories
    private function findTemplateFile($template)
    {
        foreach ($this->templateDirs as $dir) {
            $filePath = __DIR__ . '/' . trim($dir, '/') . '/' . $template;
            if (file_exists($filePath)) {
                return $filePath;
            }
        }
        return false;
    }

    // Process partials (includes)
    private function processPartials($content)
    {
        return preg_replace_callback('/{{\s*include\((.+?)\)\s*}}/', function ($matches) {
            $includeSyntax = $matches[1];
            $parts = explode(',', $includeSyntax);

            // Extract partial name
            $partialName = trim(array_shift($parts), '\"\'');
            $partialFile = $this->findTemplateFile("partials/{$partialName}.tpl");

            if (!$partialFile) {
                return "<!-- Partial '{$partialName}' not found -->";
            }

            // Extract variables passed to the partial
            $localVariables = [];
            foreach ($parts as $pair) {
                $pairParts = explode('=', $pair, 2); // Limit to two parts to avoid errors with additional `=` signs
                if (count($pairParts) === 2) {
                    $key = trim($pairParts[0]);
                    $value = trim($pairParts[1], '\"\'');
                    $localVariables[$key] = $value;
                }
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
