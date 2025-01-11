<?php
if (isset($_FILES['file'])) {
    $file_tmp = $_FILES['file']['tmp_name'];
    $file_name = $_FILES['file']['name'];
    $upload_dir = 'uploads/';

    // Create the uploads directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_path = $upload_dir . $file_name;

    // Move the uploaded file to the uploads directory
    if (move_uploaded_file($file_tmp, $file_path)) {
        try {
            // Your API key
            $api_key = "1a7ad280e25d474282c5dda2956a2fcc";

            // Upload the file to AssemblyAI
            $upload_url = upload_file($api_key, $file_path);

            // Transcribe the file in English
            $transcript_en = create_transcript($api_key, $upload_url, 'en');

            // Transcribe the file in Spanish
            $transcript_es = create_transcript($api_key, $upload_url, 'es');

            // Merge the transcripts (you can customize this as per your needs)
            $combined_transcript = "English Transcript:\n" . $transcript_en['text'] . "\n\nSpanish Transcript:\n" . $transcript_es['text'];

            // Output the combined transcript
            echo nl2br($combined_transcript);
        } catch (Exception $e) {
            echo 'Error: ' . $e->getMessage();
        }
    } else {
        echo 'Failed to upload file.';
    }
} else {
    echo 'No file uploaded.';
}

function upload_file($api_key, $path)
{
    $url = 'https://api.assemblyai.com/v2/upload';
    $data = file_get_contents($path);

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-type: application/octet-stream\r\nAuthorization: $api_key",
            'content' => $data
        ]
    ];

    $context = stream_context_create($options);
    $response = file_get_contents($url, false, $context);

    if ($http_response_header[0] == 'HTTP/1.1 200 OK') {
        $json = json_decode($response, true);
        return $json['upload_url'];
    } else {
        echo "Error: " . $http_response_header[0] . " - $response";
        return null;
    }
}

function create_transcript($api_key, $audio_url, $language_code = 'en')  // Default is English
{
    $url = "https://api.assemblyai.com/v2/transcript";

    $headers = array(
        "authorization: " . $api_key,
        "content-type: application/json"
    );

    // Add the language code to the request data
    $data = array(
        "audio_url" => $audio_url,
        "language_code" => $language_code  // Specify the language (en or es)
    );

    $curl = curl_init($url);

    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    $response = json_decode(curl_exec($curl), true);

    curl_close($curl);

    $transcript_id = $response['id'];

    $polling_endpoint = "https://api.assemblyai.com/v2/transcript/" . $transcript_id;

    while (true) {
        $polling_response = curl_init($polling_endpoint);

        curl_setopt($polling_response, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($polling_response, CURLOPT_RETURNTRANSFER, true);

        $transcription_result = json_decode(curl_exec($polling_response), true);

        if ($transcription_result['status'] === "completed") {
            return $transcription_result;
        } else if ($transcription_result['status'] === "error") {
            throw new Exception("Transcription failed: " . $transcription_result['error']);
        } else {
            sleep(3);
        }
    }
}
