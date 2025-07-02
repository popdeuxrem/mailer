<?php

declare(strict_types=1);

namespace EmailPlatform\Mail\Content;

/**
 * Advanced Spintax Processor
 * 
 * Processes spintax syntax to generate dynamic, randomized content
 * for email campaigns with sophisticated variation algorithms.
 */
class SpintaxProcessor
{
    private array $processedCache = [];
    private int $maxRecursionDepth = 10;

    /**
     * Process spintax content with recursive support
     */
    public function process(string $content): string
    {
        // Check cache first
        $cacheKey = md5($content);
        if (isset($this->processedCache[$cacheKey])) {
            return $this->processedCache[$cacheKey];
        }

        $processed = $this->processRecursive($content, 0);
        
        // Cache the result
        $this->processedCache[$cacheKey] = $processed;
        
        return $processed;
    }

    /**
     * Process spintax with recursion support
     */
    private function processRecursive(string $content, int $depth): string
    {
        if ($depth > $this->maxRecursionDepth) {
            return $content;
        }

        // Pattern to match {option1|option2|option3}
        $pattern = '/\{([^{}]*)\}/';
        
        while (preg_match($pattern, $content)) {
            $content = preg_replace_callback($pattern, function($matches) use ($depth) {
                $options = explode('|', $matches[1]);
                $selected = $options[array_rand($options)];
                
                // Process nested spintax
                return $this->processRecursive($selected, $depth + 1);
            }, $content);
        }

        return $content;
    }

    /**
     * Advanced spintax with weighted options
     * Format: {option1:weight1|option2:weight2|option3:weight3}
     */
    public function processWeighted(string $content): string
    {
        $pattern = '/\{([^{}]*)\}/';
        
        return preg_replace_callback($pattern, function($matches) {
            $options = explode('|', $matches[1]);
            $weightedOptions = [];
            
            foreach ($options as $option) {
                if (strpos($option, ':') !== false) {
                    [$text, $weight] = explode(':', $option, 2);
                    $weight = (int)$weight;
                } else {
                    $text = $option;
                    $weight = 1;
                }
                
                // Add multiple entries based on weight
                for ($i = 0; $i < $weight; $i++) {
                    $weightedOptions[] = $text;
                }
            }
            
            return $weightedOptions[array_rand($weightedOptions)];
        }, $content);
    }

    /**
     * Generate multiple variations of content
     */
    public function generateVariations(string $content, int $count = 5): array
    {
        $variations = [];
        
        for ($i = 0; $i < $count; $i++) {
            $variations[] = $this->process($content);
        }
        
        // Remove duplicates
        return array_unique($variations);
    }

    /**
     * Validate spintax syntax
     */
    public function validateSyntax(string $content): array
    {
        $errors = [];
        $openBraces = substr_count($content, '{');
        $closeBraces = substr_count($content, '}');
        
        if ($openBraces !== $closeBraces) {
            $errors[] = 'Mismatched braces: ' . $openBraces . ' opening, ' . $closeBraces . ' closing';
        }
        
        // Check for empty options
        if (preg_match('/\{\||\|\||\|\}/', $content)) {
            $errors[] = 'Empty spintax options found';
        }
        
        // Check for nested depth
        $maxDepth = $this->calculateMaxDepth($content);
        if ($maxDepth > $this->maxRecursionDepth) {
            $errors[] = "Spintax nesting too deep: $maxDepth levels (max: {$this->maxRecursionDepth})";
        }
        
        return $errors;
    }

    /**
     * Calculate maximum nesting depth
     */
    private function calculateMaxDepth(string $content): int
    {
        $maxDepth = 0;
        $currentDepth = 0;
        
        for ($i = 0; $i < strlen($content); $i++) {
            if ($content[$i] === '{') {
                $currentDepth++;
                $maxDepth = max($maxDepth, $currentDepth);
            } elseif ($content[$i] === '}') {
                $currentDepth--;
            }
        }
        
        return $maxDepth;
    }

    /**
     * Count total possible variations
     */
    public function countVariations(string $content): int
    {
        $pattern = '/\{([^{}]*)\}/';
        $totalVariations = 1;
        
        preg_match_all($pattern, $content, $matches);
        
        foreach ($matches[1] as $optionsString) {
            $options = explode('|', $optionsString);
            $totalVariations *= count($options);
        }
        
        return $totalVariations;
    }

    /**
     * Clear processing cache
     */
    public function clearCache(): void
    {
        $this->processedCache = [];
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cached_items' => count($this->processedCache),
            'memory_usage' => memory_get_usage(),
        ];
    }
}