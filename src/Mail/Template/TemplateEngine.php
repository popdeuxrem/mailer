<?php

declare(strict_types=1);

namespace EmailPlatform\Mail\Template;

/**
 * Advanced Email Template Engine
 * 
 * Handles responsive email template processing with dynamic content,
 * personalization, and modern email client compatibility.
 */
class TemplateEngine
{
    private array $templates = [];
    private array $globalVariables = [];

    /**
     * Render template with data
     */
    public function render(string $template, array $data = []): array
    {
        $htmlContent = $this->processTemplate($template, $data);
        $textContent = $this->convertToText($htmlContent);

        return [
            'html' => $htmlContent,
            'text' => $textContent
        ];
    }

    /**
     * Process template with data
     */
    private function processTemplate(string $template, array $data): string
    {
        // Merge with global variables
        $variables = array_merge($this->globalVariables, $data);

        // Process template variables
        $content = $this->replaceVariables($template, $variables);

        // Process conditionals
        $content = $this->processConditionals($content, $variables);

        // Process loops
        $content = $this->processLoops($content, $variables);

        return $content;
    }

    /**
     * Replace template variables
     */
    private function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            if (is_scalar($value)) {
                $content = str_replace('{{' . $key . '}}', (string)$value, $content);
            }
        }

        return $content;
    }

    /**
     * Process conditional blocks
     */
    private function processConditionals(string $content, array $variables): string
    {
        // Process if statements: {{#if variable}}content{{/if}}
        $pattern = '/\{\{#if\s+(\w+)\}\}(.*?)\{\{\/if\}\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($variables) {
            $variable = $matches[1];
            $content = $matches[2];
            
            if (isset($variables[$variable]) && $variables[$variable]) {
                return $content;
            }
            
            return '';
        }, $content);
    }

    /**
     * Process loop blocks
     */
    private function processLoops(string $content, array $variables): string
    {
        // Process each loops: {{#each items}}{{name}}{{/each}}
        $pattern = '/\{\{#each\s+(\w+)\}\}(.*?)\{\{\/each\}\}/s';
        
        return preg_replace_callback($pattern, function($matches) use ($variables) {
            $arrayVar = $matches[1];
            $template = $matches[2];
            $output = '';
            
            if (isset($variables[$arrayVar]) && is_array($variables[$arrayVar])) {
                foreach ($variables[$arrayVar] as $item) {
                    $itemOutput = $template;
                    if (is_array($item)) {
                        foreach ($item as $key => $value) {
                            $itemOutput = str_replace('{{' . $key . '}}', (string)$value, $itemOutput);
                        }
                    }
                    $output .= $itemOutput;
                }
            }
            
            return $output;
        }, $content);
    }

    /**
     * Convert HTML to text
     */
    private function convertToText(string $html): string
    {
        // Remove script and style elements
        $html = preg_replace('/<(script|style)[^>]*>.*?<\/\1>/si', '', $html);
        
        // Convert common HTML elements to text equivalents
        $conversions = [
            '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/si' => "\n\n*** $1 ***\n\n",
            '/<p[^>]*>(.*?)<\/p>/si' => "\n\n$1\n\n",
            '/<br[^>]*>/si' => "\n",
            '/<hr[^>]*>/si' => "\n" . str_repeat('-', 50) . "\n",
            '/<a[^>]*href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/si' => '$2 ($1)',
            '/<strong[^>]*>(.*?)<\/strong>/si' => '**$1**',
            '/<b[^>]*>(.*?)<\/b>/si' => '**$1**',
            '/<em[^>]*>(.*?)<\/em>/si' => '*$1*',
            '/<i[^>]*>(.*?)<\/i>/si' => '*$1*',
            '/<ul[^>]*>(.*?)<\/ul>/si' => "\n$1\n",
            '/<ol[^>]*>(.*?)<\/ol>/si' => "\n$1\n",
            '/<li[^>]*>(.*?)<\/li>/si' => "• $1\n",
        ];

        foreach ($conversions as $pattern => $replacement) {
            $html = preg_replace($pattern, $replacement, $html);
        }

        // Remove remaining HTML tags
        $text = strip_tags($html);

        // Clean up whitespace
        $text = preg_replace('/\n\s*\n\s*\n/', "\n\n", $text);
        $text = trim($text);

        return $text;
    }

    /**
     * Get responsive email template
     */
    public function getResponsiveTemplate(string $content, array $options = []): string
    {
        $backgroundColor = $options['background_color'] ?? '#f4f4f4';
        $containerColor = $options['container_color'] ?? '#ffffff';
        $textColor = $options['text_color'] ?? '#333333';
        $primaryColor = $options['primary_color'] ?? '#007cba';

        return '
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{subject}}</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }
        
        /* Global styles */
        body {
            margin: 0 !important;
            padding: 0 !important;
            background-color: ' . $backgroundColor . ';
            font-family: Arial, sans-serif;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: ' . $containerColor . ';
        }
        
        .email-header {
            background-color: ' . $primaryColor . ';
            color: #ffffff;
            padding: 20px;
            text-align: center;
        }
        
        .email-body {
            padding: 30px 20px;
            color: ' . $textColor . ';
            line-height: 1.6;
        }
        
        .email-footer {
            background-color: #f8f8f8;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666666;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: ' . $primaryColor . ';
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .btn:hover {
            background-color: #005a87;
        }
        
        /* Mobile responsive */
        @media only screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                max-width: 100% !important;
            }
            
            .email-body {
                padding: 20px 15px !important;
            }
            
            .btn {
                display: block !important;
                width: auto !important;
                text-align: center !important;
            }
        }
    </style>
</head>
<body>
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td>
                <div class="email-container">
                    <!-- Header -->
                    <div class="email-header">
                        <h1>{{company_name}}</h1>
                    </div>
                    
                    <!-- Body -->
                    <div class="email-body">
                        ' . $content . '
                    </div>
                    
                    <!-- Footer -->
                    <div class="email-footer">
                        <p>{{company_name}}<br>
                        {{company_address}}</p>
                        
                        <p>
                            <a href="{{unsubscribe_url}}" style="color: #666666;">Unsubscribe</a> |
                            <a href="{{preferences_url}}" style="color: #666666;">Update Preferences</a>
                        </p>
                        
                        <p style="margin-top: 15px; font-size: 11px;">
                            This email was sent to {{email}}. 
                            If you no longer wish to receive these emails, you can 
                            <a href="{{unsubscribe_url}}" style="color: #666666;">unsubscribe here</a>.
                        </p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>';
    }

    /**
     * Get predefined templates
     */
    public function getTemplates(): array
    {
        return [
            'welcome' => [
                'name' => 'Welcome Email',
                'description' => 'Welcome new subscribers',
                'content' => '
                    <h2>Welcome to {{company_name}}!</h2>
                    <p>Hi {{first_name}},</p>
                    <p>Thank you for subscribing to our newsletter. We\'re excited to have you on board!</p>
                    <p>Here\'s what you can expect from us:</p>
                    <ul>
                        <li>Weekly industry insights</li>
                        <li>Product updates and news</li>
                        <li>Exclusive offers and promotions</li>
                    </ul>
                    <p style="text-align: center; margin: 30px 0;">
                        <a href="{{website_url}}" class="btn">Visit Our Website</a>
                    </p>
                    <p>Best regards,<br>The {{company_name}} Team</p>
                '
            ],
            'newsletter' => [
                'name' => 'Newsletter Template',
                'description' => 'Regular newsletter format',
                'content' => '
                    <h2>{{newsletter_title}}</h2>
                    <p>Hi {{first_name}},</p>
                    <p>{{intro_text}}</p>
                    
                    {{#each articles}}
                    <div style="margin: 30px 0; padding: 20px; border-left: 4px solid {{primary_color}};">
                        <h3>{{title}}</h3>
                        <p>{{excerpt}}</p>
                        <a href="{{url}}">Read More →</a>
                    </div>
                    {{/each}}
                    
                    <p>Thanks for reading!</p>
                '
            ],
            'promotion' => [
                'name' => 'Promotional Email',
                'description' => 'Product promotion and offers',
                'content' => '
                    <h2>{{offer_title}}</h2>
                    <p>Hi {{first_name}},</p>
                    <p>{{offer_description}}</p>
                    
                    <div style="text-align: center; background: #f8f8f8; padding: 30px; margin: 20px 0; border-radius: 8px;">
                        <h3 style="color: {{primary_color}}; font-size: 24px;">{{discount_amount}} OFF</h3>
                        <p style="font-size: 18px;">Use code: <strong>{{promo_code}}</strong></p>
                        <p style="margin: 20px 0;">
                            <a href="{{shop_url}}" class="btn">Shop Now</a>
                        </p>
                        <p style="font-size: 12px; color: #666;">Offer expires {{expiry_date}}</p>
                    </div>
                    
                    <p>Don\'t miss out on this limited-time offer!</p>
                '
            ]
        ];
    }

    /**
     * Set global template variables
     */
    public function setGlobalVariables(array $variables): void
    {
        $this->globalVariables = array_merge($this->globalVariables, $variables);
    }

    /**
     * Validate template syntax
     */
    public function validateTemplate(string $template): array
    {
        $errors = [];

        // Check for unclosed tags
        $openTags = preg_match_all('/\{\{#\w+/', $template);
        $closeTags = preg_match_all('/\{\{\/\w+/', $template);

        if ($openTags !== $closeTags) {
            $errors[] = 'Unclosed template tags detected';
        }

        // Check for invalid variable syntax
        if (preg_match('/\{\{[^}]*\{\{/', $template)) {
            $errors[] = 'Nested template variables are not allowed';
        }

        return $errors;
    }
}