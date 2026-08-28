<?php
/**
 * Replicate xAI Grok Imagine Image 2 Handler
 * Handles image generation/editing using the xai/grok-imagine-image-2 model
 */

class ReplicateGrokImagineHandler {

    private $startTime;
    private $api_key;

    public function __construct($apikey) {
        $this->startTime = microtime(true);
        $this->initializeEnvironment($apikey);
    }

    /**
     * Initialize required environment and dependencies
     */
    private function initializeEnvironment($apikey) {
        error_reporting(E_ALL);

        $this->api_key = $apikey;
    }

    /**
     * Process the image generation/editing request
     *
     * @param array $requestData The request data containing prompt and optional image
     * @return array Response data
     */
    public function process($requestData) {
        try {
            $prompt = $requestData['prompt'] ?? "";

            $imageData = null;
            if (isset($requestData['image'])) {
                $imageData = $requestData['image'];
            }

            $payload = $this->preparePayload($prompt, $imageData, $requestData);
            $responseData = $this->callReplicateAPI($payload);
            $outputPath = $this->saveOutput($responseData);

            return [
                "status" => "success",
                "message" => "Image generated successfully",
                "output_url" => $outputPath,
                "processing_time" => round(microtime(true) - $this->startTime, 2)
            ];

        } catch (Exception $e) {
            error_log("ReplicateGrokImagineHandler Error: " . $e->getMessage());
            return [
                "status" => "error",
                "message" => $e->getMessage()
            ];
        }
    }

    /**
     * Prepare the payload for the Replicate API
     */
    private function preparePayload($prompt, $imageData, $requestData) {
        $enhancedPrompt = $prompt;
        $imageInput = null;

        if ($imageData) {
            $imageInput = $this->getImageAsDataUri($imageData['path'], $imageData['filename'] ?? null);

            $descriptionResult = $this->describeSourcePicture($imageInput);
            if ($descriptionResult['status'] === 'success') {
                $enhancedPrompt = "Core features: " . $descriptionResult['description'] . "\n\n" .
                    "Instruction: Convert image1 to a realistic style,8k masterpiece, Reimagine the whole picture, while preserving details like , skin color, eye color, hair style, hair color, clothing, make-up , body proportions and environment." .
                    ($prompt ? "\n\nAdditional notes by human user: $prompt" : "");
            }
        }

        $payload = [
            "input" => [
                "prompt" => $enhancedPrompt,
                "aspect_ratio" => $requestData['aspect_ratio'] ?? "1:1",
                "quality" => $requestData['quality'] ?? "medium",
                "resolution" => $requestData['resolution'] ?? "2k"
            ]
        ];

        if ($imageInput) {
            $payload["input"]["image"] = $imageInput;
        }

        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Convert a local file or URL to a base64 data URI for inline API usage
     */
    private function getImageAsDataUri($filePath, $filename = null) {
        $localPath = $this->convertUrlToLocalPath($filePath);

        if (!file_exists($localPath)) {
            throw new Exception("Image file not found: $localPath");
        }

        $imageContent = file_get_contents($localPath);
        if ($imageContent === false) {
            throw new Exception("Failed to read image file: $localPath");
        }

        $base64Image = base64_encode($imageContent);
        $mimeType = $this->getMimeType($base64Image);

        return "data:$mimeType;base64,$base64Image";
    }

    /**
     * Upload image from file path to Replicate file upload endpoint
     */
    public function uploadImageFromPath($filePath, $filename = null) {
        $localPath = $this->convertUrlToLocalPath($filePath);

        if (!file_exists($localPath)) {
            throw new Exception("Image file not found: $localPath");
        }

        if (!$filename) {
            $filename = basename($localPath);
        }

        $imageContent = file_get_contents($localPath);
        if ($imageContent === false) {
            throw new Exception("Failed to read image file: $localPath");
        }

        $base64Image = base64_encode($imageContent);
        return $this->uploadImageToReplicate($base64Image, $filename);
    }

    /**
     * Convert URL to local file path
     */
    private function convertUrlToLocalPath($path) {
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            $urlParts = parse_url($path);
            $urlPath = $urlParts['path'] ?? '';

            $serverRoot = '/var/www/html';

            if (strpos($urlPath, '/HerikaServer/') === 0) {
                return $serverRoot . $urlPath;
            }

            if (strpos($urlPath, 'HerikaServer') !== false) {
                $parts = explode('HerikaServer', $urlPath);
                return $serverRoot . '/HerikaServer' . $parts[1];
            }
        }

        return $path;
    }

    /**
     * Upload image to Replicate file upload endpoint
     */
    private function uploadImageToReplicate($base64Image, $filename) {
        $imageContent = base64_decode($base64Image, true);
        if ($imageContent === false) {
            throw new Exception("Failed to decode base64 image data");
        }

        if (!$filename) {
            $filename = 'image_' . uniqid() . '.png';
        }

        $tmpFile = tempnam('/var/www/html/HerikaServer/soundcache/', 'replicate_');
        if (!file_put_contents($tmpFile, $imageContent)) {
            error_log("Failed to write temporary file for upload: $tmpFile");
            throw new Exception("Failed to write temporary file for upload: $tmpFile");
        }
        error_log("Temporary file created for upload: $tmpFile source filename: $filename");
        try {
            $cFile = new CURLFile($tmpFile, $this->getMimeType($base64Image), $filename);

            $ch = curl_init("https://api.replicate.com/v1/files");

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Token {$this->api_key}",
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'content' => $cFile,
                'metadata' => json_encode(['filename' => $filename])
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);

            $output = curl_exec($ch);

            if (curl_errno($ch)) {
                throw new Exception("cURL Error uploading file: " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 && $httpCode !== 201) {
                throw new Exception("File upload API error: HTTP $httpCode - $output");
            }

            $responseData = json_decode($output, true);

            if (!isset($responseData['urls']['get'])) {
                throw new Exception("No file URL in upload response: " . $output);
            }

            error_log("Replicate file upload successful: " . $responseData['urls']['get']);

            return $responseData['urls']['get'];

        } finally {
            if (file_exists($tmpFile)) {
                //unlink($tmpFile);
                error_log("Temporary file not deleted: $tmpFile");
            }
        }
    }

    /**
     * Get MIME type from base64 header
     */
    private function getMimeType($base64Image) {
        $header = substr($base64Image, 0, 8);
        $decodedHeader = base64_decode($header, true);
        if ($decodedHeader !== false) {
            $bytes = unpack('H*', $decodedHeader);
            $hex = $bytes[1];

            if (strpos($hex, 'ffd8ff') === 0) return 'image/jpeg';
            if (strpos($hex, '89504e47') === 0) return 'image/png';
            if (strpos($hex, '474946') === 0) return 'image/gif';
            if (strpos($hex, '424d') === 0) return 'image/bmp';
            if (strpos($hex, '52494646') === 0) return 'image/webp';
        }

        return 'image/jpeg';
    }

    /**
     * Call the Replicate API
     */
    private function callReplicateAPI($payload) {
        $ch = curl_init("https://api.replicate.com/v1/models/xai/grok-imagine-image-2/predictions");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->api_key}",
            "Content-Type: application/json",
            "Prefer: wait",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $output = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($output, true);

        if (!$responseData) {
            throw new Exception("Failed to parse API response");
        }

        error_log("Grok Imagine API Response: " . print_r($responseData, true));

        if ($httpCode === 202) {
            error_log("Grok Imagine prediction still processing, polling for result...");
            $responseData = $this->pollReplicatePrediction($responseData['urls']['get']);
        }

        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {
            throw new Exception("API Error: HTTP $httpCode - $output");
        }

        return $responseData;
    }

    /**
     * Save the output image
     */
    private function saveOutput($responseData) {
        if (!isset($responseData['output']) || empty($responseData['output'])) {
            throw new Exception("No output image in API response");
        }

        $outputDirectory = '/var/www/html/HerikaServer/data/pictures/gallery/';

        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }

        $imageData = is_array($responseData['output']) ? $responseData['output'][0] : $responseData['output'];

        $extension = 'png';
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            $urlParts = parse_url($imageData);
            $pathParts = pathinfo($urlParts['path']);
            $extension = $pathParts['extension'] ?? 'png';
        }

        $filename = $outputDirectory . uniqid('grok_imagine_', true) . '.' . $extension;

        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            $context = stream_context_create([
                'http' => ['timeout' => 30]
            ]);
            $imageContent = @file_get_contents($imageData, false, $context);
            if ($imageContent === false) {
                throw new Exception("Failed to download output image from URL");
            }
            file_put_contents($filename, $imageContent);
        } else {
            if (!file_exists($imageData)) {
                throw new Exception("Output image file not found: $imageData");
            }
            copy($imageData, $filename);
        }

        return "/var/www/html/HerikaServer/data/pictures/gallery/" . basename($filename);
    }

    /**
     * Describe source picture using an inline image data URI
     */
    public function describeSourcePicture($imageInput) {
        try {
            $payload = [
                "input" => [
                    "dynamic_thinking" => false,
                    "images" => [$imageInput],
                    "max_output_tokens" => 2048,
                    "prompt" => "Describe the core physical features of the character in this image. Include: skin tone/color, eye color, hair color, hairstyle, visible facial features (nose, lips, cheekbones, tattoos), body type if visible, and any other distinctive physical characteristics. Be specific, short and concise,avoid hyperbole (piercing, glowing,...). Note if it's a fantasy character or similar, some features can be fantastical.",
                    "temperature" => 1,
                    "top_p" => 0.95,
                    "videos" => []
                ]
            ];

            $description = ''; // $this->callGeminiVisionAPI($payload);

            return [
                "status" => "success",
                "description" => $description,
                "processing_time" => round(microtime(true) - $this->startTime, 2)
            ];

        } catch (Exception $e) {
            error_log("ReplicateGrokImagineHandler describeSourcePicture Error: " . $e->getMessage());
            return [
                "status" => "error",
                "message" => $e->getMessage()
            ];
        }
    }

    /**
     * Call the Google Gemini 2.5 Flash vision API via Replicate
     */
    private function callGeminiVisionAPI($payload) {
        $ch = curl_init("https://api.replicate.com/v1/models/google/gemini-2.5-flash/predictions");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->api_key}",
            "Content-Type: application/json",
            "Prefer: wait",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES));
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $output = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($output, true);

        if (!$responseData) {
            throw new Exception("Failed to parse Gemini Vision API response");
        }

        error_log("Gemini 2.5 Flash Vision API Response: " . print_r($responseData, true));

        if ($httpCode === 202) {
            error_log("Gemini prediction still processing, polling for result...");
            $responseData = $this->pollReplicatePrediction($responseData['urls']['get']);
        }

        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {
            throw new Exception("Gemini Vision API Error: HTTP $httpCode - $output");
        }

        if (isset($responseData['output']) && is_array($responseData['output'])) {
            return implode("", $responseData['output']);
        }

        if (isset($responseData['output'])) {
            return $responseData['output'];
        }

        throw new Exception("No output in Gemini Vision API response");
    }

    /**
     * Poll Replicate prediction URL until completion
     */
    private function pollReplicatePrediction($statusUrl, $maxRetries = 120, $delaySeconds = 1) {
        $retries = 0;

        while ($retries < $maxRetries) {
            sleep($delaySeconds);
            $retries++;

            $ch = curl_init($statusUrl);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$this->api_key}",
                "Content-Type: application/json",
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $output = curl_exec($ch);

            if (curl_errno($ch)) {
                curl_close($ch);
                throw new Exception("cURL Error polling prediction: " . curl_error($ch));
            }

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $responseData = json_decode($output, true);

            if (!$responseData) {
                throw new Exception("Failed to parse prediction status response");
            }

            error_log("Prediction poll attempt $retries: Status = " . ($responseData['status'] ?? 'unknown'));

            if (isset($responseData['status'])) {
                if ($responseData['status'] === 'succeeded') {
                    error_log("Grok Imagine prediction succeeded after $retries attempts");
                    return $responseData;
                }

                if ($responseData['status'] === 'failed' || $responseData['status'] === 'canceled') {
                    $errorMsg = $responseData['error'] ?? 'Unknown error';
                    throw new Exception("Grok Imagine prediction " . $responseData['status'] . ": " . $errorMsg);
                }
            }

            if ($httpCode === 200 || $httpCode === 202) {
                continue;
            }

            throw new Exception("Unexpected HTTP code $httpCode while polling: " . $output);
        }

        throw new Exception("Grok Imagine prediction polling timed out after $maxRetries attempts");
    }
}
?>
