<?php
/**
 * Replicate Flux 2 Klein 9B Handler
 * Handles image generation using the Black Forest Labs Flux 2 Klein 9B model
 */

class ReplicateFluxKleinHandler {
    
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
     * @param array $requestData The request data containing prompt and optional images
     * @return array Response data
     */
    public function process($requestData) {
        try {
            // Validate input
            
            $prompt = $requestData['prompt']??"";
            
            // Handle images if provided
            $imageData = null;
            if (isset($requestData['image'])) {
                $imageData = $requestData['image'];
            }
            
            $imageData2 = null;
            if (isset($requestData['image2'])) {
                $imageData2 = $requestData['image2'];
            }
            
            // Prepare payload with optional parameters
            $payload = $this->preparePayload($prompt, $imageData, $imageData2, $requestData);
            
            // Make API call
            $responseData = $this->callReplicateAPI($payload);
            
            // Save output image
            $outputPath = $this->saveOutput($responseData);
            
            return [
                "status" => "success",
                "message" => "Image generated successfully",
                "output_url" => $outputPath,
                "processing_time" => round(microtime(true) - $this->startTime, 2)
            ];
            
        } catch (Exception $e) {
            error_log("ReplicateFluxKleinHandler Error: " . $e->getMessage());
            return [
                "status" => "error",
                "message" => $e->getMessage()
            ];
        }
    }
    
    /**
     * Prepare the payload for the Replicate API
     */
    private function preparePayload($prompt, $imageData, $imageData2, $requestData) {
        $images = [];
        $enhancedPrompt = $prompt;
        
        // If first image is provided, get its description and prepend to prompt
        if ($imageData) {
            $uploadedImagePath = $this->uploadImageFromPath($imageData['path'], $imageData['filename'] ?? null);
            $images[] = $uploadedImagePath;
            
            // Get description of the source image
            $descriptionResult = $this->describeSourcePicture($imageData['path']);
            if ($descriptionResult['status'] === 'success') {
                $enhancedPrompt = "Core features: " . $descriptionResult['description'] . "\n\n" . 
                "Instruction: Convert image1 to a realistic style,8k masterpiece, Reimagine the whole picture, while preserving details like , skin color, eye color, hair style, hair color, clothing, make-up , body proportions and environment.".
                ($prompt?"\n\nAdditional notes by human user: $prompt":"");
            }
        }
        
        // If second image is provided, upload it and add to payload
        if ($imageData2) {
            $uploadedImagePath2 = $this->uploadImageFromPath($imageData2['path'], $imageData2['filename'] ?? null);
            $images[] = $uploadedImagePath2;
        }
        
        $payload = [
            "input" => [
                "images" => $images,
                "prompt" => $enhancedPrompt,
                "go_fast" => $requestData['go_fast'] ?? true,
                "aspect_ratio" => $requestData['aspect_ratio'] ?? "1:1",
                "output_format" => $requestData['output_format'] ?? "png",
                "output_quality" => $requestData['output_quality'] ?? 95,
                "output_megapixels" => $requestData['output_megapixels'] ?? "1",
                "disable_safety_checker" => $requestData['disable_safety_checker'] ?? true
            ]
        ];
        
        return json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
    
    
    /**
     * Upload image from file path to Replicate file upload endpoint
     */
    public function uploadImageFromPath($filePath, $filename = null) {
        // Convert URL to local file path if needed
        $localPath = $this->convertUrlToLocalPath($filePath);
        
        if (!file_exists($localPath)) {
            throw new Exception("Image file not found: $localPath");
        }
        
        if (!$filename) {
            $filename = basename($localPath);
        }
        
        // Read file and encode to base64
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
        // Check if it's a URL
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            // Extract the path component from the URL
            $urlParts = parse_url($path);
            $urlPath = $urlParts['path'] ?? '';
            
            // Get the server root
            $serverRoot = '/var/www/html';
            
            // Convert /HerikaServer/... to local path
            if (strpos($urlPath, '/HerikaServer/') === 0) {
                return $serverRoot . $urlPath;
            }
            
            // Try to find HerikaServer in the path
            if (strpos($urlPath, 'HerikaServer') !== false) {
                $parts = explode('HerikaServer', $urlPath);
                return $serverRoot . '/HerikaServer' . $parts[1];
            }
        }
        
        // Return as-is if it's already a local path
        return $path;
    }
    
    /**
     * Upload image to Replicate file upload endpoint
     */
    private function uploadImageToReplicate($base64Image, $filename) {
        // Decode base64 to binary
        $imageContent = base64_decode($base64Image, true);
        if ($imageContent === false) {
            throw new Exception("Failed to decode base64 image data");
        }
        
        // Create temporary file
        $tmpFile = tempnam(sys_get_temp_dir(), 'replicate_');
        file_put_contents($tmpFile, $imageContent);
        
        try {
            // Create multipart form data
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
            // Clean up temporary file
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
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
        $ch = curl_init("https://api.replicate.com/v1/models/black-forest-labs/flux-2-klein-9b/predictions");
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->api_key}",
            "Content-Type: application/json",
            "Prefer: wait",
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout
        
        $output = curl_exec($ch);
        
        // Check for cURL errors
        if (curl_errno($ch)) {
            throw new Exception("cURL Error: " . curl_error($ch));
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $responseData = json_decode($output, true);
        
        if (!$responseData) {
            throw new Exception("Failed to parse API response");
        }
        
        error_log("Flux Klein API Response: " . print_r($responseData, true));
        
        // Handle 202 Accepted - prediction is still processing
        if ($httpCode === 202) {
            error_log("Flux Klein prediction still processing, polling for result...");
            $responseData = $this->pollReplicatePrediction($responseData['urls']['get']);
        }
        
        // Check HTTP response code
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
        
        $outputDirectory ='/var/www/html/HerikaServer/data/pictures/gallery/';        

        // Ensure directory exists
        if (!is_dir($outputDirectory)) {
            mkdir($outputDirectory, 0755, true);
        }
        
        // Handle both single output and array of outputs
        $imageData = is_array($responseData['output']) ? $responseData['output'][0] : $responseData['output'];
        
        // Determine file extension
        $extension = 'png';
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            $urlParts = parse_url($imageData);
            $pathParts = pathinfo($urlParts['path']);
            $extension = $pathParts['extension'] ?? 'png';
        }
        
        $filename = $outputDirectory . uniqid('flux_klein_', true) . '.' . $extension;
        
        if (filter_var($imageData, FILTER_VALIDATE_URL)) {
            // Download from URL
            $context = stream_context_create([
                'http' => ['timeout' => 30]
            ]);
            $imageContent = @file_get_contents($imageData, false, $context);
            if ($imageContent === false) {
                throw new Exception("Failed to download output image from URL");
            }
            file_put_contents($filename, $imageContent);
        } else {
            // Assume it's a local path
            if (!file_exists($imageData)) {
                throw new Exception("Output image file not found: $imageData");
            }
            copy($imageData, $filename);
        }
        
        return "/var/www/html/HerikaServer/data/pictures/gallery/" . basename($filename); 
    }
    
    /**
     * Describe source picture using Google Gemini 2.5 Flash vision capabilities
     * Analyzes image and returns description of physical features
     * 
     * @param string $imagePath Path or URL to the image
     * @return array Response containing the description
     */
    public function describeSourcePicture($imagePath) {
        try {
            // Upload image to get a Replicate URL
            $imageUrl = $this->uploadImageFromPath($imagePath, basename($imagePath));
            
            // Create payload for Gemini 2.5 Flash analysis
            $payload = [
                "input" => [
                    "dynamic_thinking" => false,
                    "images" => [$imageUrl],
                    "max_output_tokens" => 2048,
                    "prompt" => "Describe the core physical features of the character in this image. Include: skin tone/color, eye color, hair color, hairstyle, visible facial features (nose, lips, cheekbones, tattoos), body type if visible, and any other distinctive physical characteristics. Be specific, short and concise,avoid hyperbole (piercing, glowing,...). Note if it's a fantasy character or similar, some features can be fantastical.",
                    "temperature" => 1,
                    "top_p" => 0.95,
                    "videos" => []
                ]
            ];
            
            // Call the Replicate API with Gemini 2.5 Flash
            //$description = $this->callGeminiVisionAPI($payload);
            
            $description='';
            
            return [
                "status" => "success",
                "description" => $description,
                "processing_time" => round(microtime(true) - $this->startTime, 2)
            ];
            
        } catch (Exception $e) {
            error_log("ReplicateFluxKleinHandler describeSourcePicture Error: " . $e->getMessage());
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 5 minute timeout
        
        $output = curl_exec($ch);
        
        // Check for cURL errors
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
        
        // Handle 202 Accepted - prediction is still processing
        if ($httpCode === 202) {
            error_log("Gemini prediction still processing, polling for result...");
            $responseData = $this->pollReplicatePrediction($responseData['urls']['get']);
        }
        
        // Check HTTP response code
        if ($httpCode !== 200 && $httpCode !== 201 && $httpCode !== 202) {
            throw new Exception("Gemini Vision API Error: HTTP $httpCode - $output");
        }
        
        // Extract text from response
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
            
            // Check if prediction is complete
            if (isset($responseData['status'])) {
                if ($responseData['status'] === 'succeeded') {
                    error_log("MiniCPM prediction succeeded after $retries attempts");
                    return $responseData;
                }
                
                if ($responseData['status'] === 'failed' || $responseData['status'] === 'canceled') {
                    $errorMsg = $responseData['error'] ?? 'Unknown error';
                    throw new Exception("MiniCPM prediction " . $responseData['status'] . ": " . $errorMsg);
                }
            }
            
            // Still processing, continue polling
            if ($httpCode === 200 || $httpCode === 202) {
                continue;
            }
            
            throw new Exception("Unexpected HTTP code $httpCode while polling: " . $output);
        }
        
        throw new Exception("MiniCPM prediction polling timed out after $maxRetries attempts");
    }
}
?>
