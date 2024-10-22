<?php

namespace TuanAnh\LaravelTikTok;

use Illuminate\Support\Facades\Session;
use JsonException;
use RuntimeException;
use TuanAnh\LaravelTikTok\Responses\TokenInfo;
use TuanAnh\LaravelTikTok\Responses\CreatorQuery;
use TuanAnh\LaravelTikTok\Responses\PublishStatus;
use Exception;

class Connector
{
    // Base URLs used for the API calls
    public const BASE_V2              = 'https://open.tiktokapis.com/v2/';
    public const BASE_REDIRECT_URL    = 'https://www.tiktok.com/v2/auth/authorize/?client_key=%s&scope=%s&response_type=code&redirect_uri=%s&state=%s';
    public const BASE_AUTH_URL        = self::BASE_V2.'oauth/token/';
    public const BASE_USER_URL        = self::BASE_V2.'user/info/?fields=%s';
    public const BASE_VIDEOS_URL      = self::BASE_V2.'video/list/?fields=%s';
    public const BASE_VIDEO_QUERY_URL = self::BASE_V2.'video/query/?fields=%s';
    public const BASE_CREATOR_QUERY   = self::BASE_V2.'post/publish/creator_info/query/';
    public const BASE_POST_PUBLISH    = self::BASE_V2.'post/publish/video/init/';
    public const BASE_PUBLISH_STATUS  = self::BASE_V2.'post/publish/status/fetch/';
    public const BASE_PHOTO_PUBLISH   = self::BASE_V2.'post/publish/content/init/';

    // Name of the Session used to store the State. This is required to prevent CSRF attacks
    public const SESS_STATE = 'TIKTOK_STATE';

    // Name of the GET parameter we expect containing the authorization code to verify
    public const CODE_PARAM = 'code';

    // Permissions List
    public const PERMISSION_USER_BASIC    = 'user.info.basic';
    public const PERMISSION_USER_PROFILE  = 'user.info.profile';
    public const PERMISSION_USER_STATS    = 'user.info.stats';
    public const PERMISSION_VIDEO_LIST    = 'video.list';
    public const PERMISSION_SHARE_SOUND   = 'share.sound.create';
    public const PERMISSION_VIDEO_PUBLISH = 'video.publish';
    public const VALID_PERMISSIONS        = [
        self::PERMISSION_USER_BASIC, self::PERMISSION_VIDEO_LIST, self::PERMISSION_SHARE_SOUND,
        self::PERMISSION_USER_PROFILE, self::PERMISSION_USER_STATS, self::PERMISSION_VIDEO_PUBLISH
    ];

    // Fields for User
    public const FIELD_U_OPENID        = 'open_id';
    public const FIELD_U_UNION_ID      = 'union_id';
    public const FIELD_U_AVATAR        = 'avatar_url';
    public const FIELD_U_AVATAR_THUMB  = 'avatar_url_100';
    public const FIELD_U_AVATAR_LARGER = 'avatar_large_url';
    public const FIELD_U_DISPLAY_NAME  = 'display_name';
    public const FIELD_U_BIO           = 'bio_description';
    public const FIELD_U_URL           = 'profile_deep_link';
    public const FIELD_U_IS_VERIFIED   = 'is_verified';
    public const FIELD_U_FOLLOWERS     = 'follower_count';
    public const FIELD_U_FOLLOWING     = 'following_count';
    public const FIELD_U_LIKES         = 'likes_count';
    public const FIELD_U_NUM_VIDEOS    = 'video_count';
    public const FIELDS_U_ALL          = [
        self::FIELD_U_OPENID, self::FIELD_U_UNION_ID, self::FIELD_U_AVATAR, self::FIELD_U_AVATAR_THUMB,
        self::FIELD_U_AVATAR_LARGER, self::FIELD_U_DISPLAY_NAME, self::FIELD_U_BIO, self::FIELD_U_URL,
        self::FIELD_U_IS_VERIFIED, self::FIELD_U_FOLLOWERS, self::FIELD_U_FOLLOWING, self::FIELD_U_LIKES,
        self::FIELD_U_NUM_VIDEOS
    ];

    // Fields for Video
    public const FIELD_EMBED_HTML = "embed_html";
    public const FIELD_EMBED_LINK = "embed_link";
    public const FIELD_LIKES      = "like_count";
    public const FIELD_COMMENTS   = "comment_count";
    public const FIELD_SHARES     = "share_count";
    public const FIELD_VIEWS      = "view_count";
    public const FIELD_TITLE      = "title";
    public const FIELD_TIME       = "create_time";
    public const FIELD_IMAGE      = "cover_image_url";
    public const FIELD_URL        = "share_url";
    public const FIELD_CAPTION    = "video_description";
    public const FIELD_DURATION   = "duration";
    public const FIELD_HEIGHT     = "height";
    public const FIELD_WIDTH      = "width";
    public const FIELD_ID         = "id";
    public const FIELDS_ALL       = [
        self::FIELD_ID, self::FIELD_WIDTH, self::FIELD_HEIGHT, self::FIELD_DURATION, self::FIELD_CAPTION,
        self::FIELD_URL, self::FIELD_IMAGE, self::FIELD_TIME, self::FIELD_EMBED_HTML, self::FIELD_EMBED_LINK,
        self::FIELD_LIKES, self::FIELD_COMMENTS, self::FIELD_SHARES, self::FIELD_VIEWS, self::FIELD_TITLE
    ];

    // Privacy Options for publishing posts
    public const PRIVACY_PUBLIC    = 'PUBLIC_TO_EVERYONE';
    public const PRIVACY_FRIENDS   = 'MUTUAL_FOLLOW_FRIENDS';
    public const PRIVACY_FOLLOWERS = 'FOLLOWER_OF_CREATOR';
    public const PRIVACY_PRIVATE   = 'SELF_ONLY';
    public const VALID_PRIVACY     = [
        self::PRIVACY_PUBLIC, self::PRIVACY_FRIENDS, self::PRIVACY_FOLLOWERS, self::PRIVACY_PRIVATE
    ];

    // .ini file configuration
    public const INI_CLIENT_ID     = 'client_id';
    public const INI_CLIENT_SECRET = 'client_secret';
    public const INI_REDIRECT_URI  = 'redirect_uri';
    public const INI_REQUIRED      = [self::INI_CLIENT_ID, self::INI_CLIENT_SECRET, self::INI_REDIRECT_URI];

    private string $client_id;
    private string $client_secret;
    private string $redirect;
    private string $token;
    private string $openid;

    /**
     * Main constructor
     *
     * Requires the basic configuration required by TikTok Apis.
     *
     * @param  string  $client_id  The Client ID provided by TikTok Developer Portal
     * @param  string  $client_secret  The Client Secret provided by TikTok Developer Portal
     * @param  string  $redirect_uri  Redirect URI approved on the Developer Portal
     */
    public function __construct(string $client_id, string $client_secret, string $redirect_uri)
    {
        $this->client_id     = $client_id;
        $this->client_secret = $client_secret;
        $this->redirect      = $redirect_uri;
    }

    /**
     * Alternative constructor based on an .ini file
     *
     * Retrieves the .ini file, parses the required parameters, then returns the object via the standard constructor
     *
     * @param  string  $path
     * @return Connector self
     * @throws Exception If the path is not found or the .ini file doesn't contain all the required parameters
     */
    public static function fromIni(string $path): Connector
    {
        if (!file_exists($path)) {
            throw new RuntimeException('Ini file not found in requested path: '.$path);
        }
        $cfg = parse_ini_file($path);
        foreach (self::INI_REQUIRED as $required_info) {
            if (!isset($cfg[$required_info])) {
                throw new RuntimeException('Ini file is missing required info: '.$required_info);
            }
        }
        return new self($cfg[self::INI_CLIENT_ID], $cfg[self::INI_CLIENT_SECRET], $cfg[self::INI_REDIRECT_URI]);
    }

    /**
     * Gets the redirect URI for frontend usage
     *
     * Generates the Redirect URI. This should be used in the frontend to redirect the user to TikTok to accept the API connection/permissions
     *
     * @param  array  $permissions  an array containing all the permissions you want to use. Your app must be approved for these permissions
     * @return string the URL to which you need to redirect the user
     * @throws Exception If the requested permissions are wrongly formatter
     */
    public function getRedirect(array $permissions = [self::PERMISSION_USER_BASIC]): string
    {
        foreach ($permissions as $permission) {
            if (!in_array($permission, self::VALID_PERMISSIONS, true)) {
                throw new RuntimeException('Invalid Permission Requested. Valid permissions are: '.implode(", ",
                        self::VALID_PERMISSIONS));
            }
        }

        $state = uniqid();
        Session::put(self::SESS_STATE, $state);
        return sprintf(self::BASE_REDIRECT_URL, $this->client_id, implode(",", $permissions),
            urlencode($this->redirect), $state);
    }

    /**
     * Checks the GET parameters to see if I am receiving a Response from TikTok with the authorisation code
     *
     * @return bool true if I am receiving an authorisation code and I should validate it
     */
    public static function receivingResponse(): bool
    {
        return isset($_GET[self::CODE_PARAM]) && $_GET[self::CODE_PARAM];
    }

    /**
     * Calls the TikTok APIs to verify an authorisation code and retrieve the access token
     *
     * First checks to validate the STATE variable. The GET of the state should be the same as the SESSION to prevent CSRF attacks
     * Then calls the TikTok APIs to verify the received authorisation code and exchange it for an Access Token
     * Set the Token and User ID within the class for further use
     *
     * @param  string  $code  contains the code received via the GET parameter
     * @param  string  $state  contains the code received via the GET parameter
     * @return TokenInfo the Access Token Info
     * @throws Exception If the STATE is not valid or if the API return error
     */
    public function verifyCode(string $code, string $state): TokenInfo
    {
        if (!Session::exists(self::SESS_STATE)) {
            throw new RuntimeException('Missing State Session');
        }
        if (Session::get(self::SESS_STATE) !== $state) {
            throw new RuntimeException('Invalid State Variable, Session: '.Session::get(self::SESS_STATE).' vs ',
                $state);
        }

        try {
            $data  = [
                'client_key'    => $this->client_id,
                'client_secret' => $this->client_secret,
                'code'          => $code,
                'grant_type'    => 'authorization_code',
                'redirect_uri'  => $this->redirect
            ];
            $res   = self::post(self::BASE_AUTH_URL, $data);
            $json  = json_decode($res, false, 512, JSON_THROW_ON_ERROR);
            $token = TokenInfo::fromJson($json);
            if ($token->getAccessToken()) {
                $this->setToken($token->getAccessToken());
                $this->setOpenID($token->getOpenId());
                return $token;
            }

            throw new RuntimeException('TikTok Api Error: '.$json->error_description);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Sets the Access Token received after authorisation for further use
     *
     * @param  string  $token  The access Token received by the APIs
     * @return void
     */
    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    /**
     * Sets the User ID received after authorisation for further use
     *
     * @param  string  $openid  The User ID received by the APIs
     * @return void
     */
    public function setOpenID(string $openid): void
    {
        $this->openid = $openid;
    }

    /**
     * Gets the User Open ID
     *
     * @return string the Open ID
     */
    public function getOpenID(): string
    {
        return $this->openid;
    }

    /**
     * Gets the Access Token, if set
     *
     * @return string
     */
    public function getToken(): string
    {
        if (empty($this->token)) {
            return '';
        }
        return $this->token;
    }

    /**
     * Retrieves the updated Access Token from the Refresh Token
     *
     * @param  string  $refresh_token  the refresh token
     * @return TokenInfo|false contains the info about the token
     */
    public function refreshToken(string $refresh_token): TokenInfo|false
    {
        try {
            $data  = [
                'client_key'    => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refresh_token
            ];
            $res   = self::post(self::BASE_AUTH_URL, $data);
            $json  = json_decode($res, false, 512, JSON_THROW_ON_ERROR);
            $token = TokenInfo::fromJson($json);
            if (!$token->getAccessToken()) {
                return false;
            }
            $this->setToken($token->getAccessToken());
            return $token;
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Calls the TikTok APIs to retrieve all available user information for the logged user
     *
     * @param  array  $fields  array containing all the fields you want to retrieve
     * @return object the JSON containing the user data
     * @throws Exception If the API returns an error
     */
    public function getUserInfo(
        array $fields = [
            self::FIELD_U_OPENID, self::FIELD_U_UNION_ID, self::FIELD_U_AVATAR, self::FIELD_U_DISPLAY_NAME
        ]
    ): object {
        foreach ($fields as $f) {
            if (!in_array($f, self::FIELDS_U_ALL, true)) {
                throw new RuntimeException('TikTok Api Error: Invalid field '.$f);
            }
        }
        try {
            $url = sprintf(self::BASE_USER_URL, implode(',', $fields));
            $res = $this->getWithAuth($url);
            return json_decode($res, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Calls the TikTok APIs to retrieve available videos for the logged user
     *
     * @param  int  $cursor  contains the cursor for pagination
     * @param  int  $num_results  max results returned by the API
     * @return object the JSON containing the user data
     * @throws Exception If the API returns an error
     */
    public function getUserVideosInfo(int $cursor = 0, int $num_results = 20, array $fields = self::FIELDS_ALL): object
    {
        if ($num_results < 1) {
            $num_results = 20;
        }
        try {
            $data = [
                'cursor'    => $cursor,
                'max_count' => $num_results
            ];
            $url  = sprintf(self::BASE_VIDEOS_URL, implode(',', $fields));
            $res  = $this->postWithAuth($url, $data);
            return json_decode($res, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Retrieves information about a single video
     *
     * @param  integer  $video_id  the id of the video
     * @param  array  $fields  array containing the list of fields you'd like returned
     * @return object the JSON info of the video or empty
     */
    public function getSingleVideoInfo(int $video_id, array $fields = self::FIELDS_ALL): object
    {
        try {
            $data = [
                'filters' => [
                    'video_ids' => [(string) $video_id]
                ]
            ];
            $url  = sprintf(self::BASE_VIDEO_QUERY_URL, implode(',', $fields));
            $res  = $this->postWithAuth($url, $data);
            $json = json_decode($res, false, 512, JSON_THROW_ON_ERROR);
            if (empty($json->data->videos[0])) {
                return json_decode('{}', false, 512, JSON_THROW_ON_ERROR);
            }
            return $json->data->videos[0];
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Retrieves a single Video object after retrieving the JSON from getSingleVideoInfo
     *
     * @param  integer  $video_id  the id of the video
     * @param  array  $fields  array containing the list of fields you'd like returned
     * @return Video the Video object with all the information
     * @throws Exception
     */
    public function getSingleVideo(int $video_id, array $fields = self::FIELDS_ALL): Video
    {
        try {
            $json = $this->getSingleVideoInfo($video_id, $fields);
            return Video::fromJson($json);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * After Calling the TikTok API via the getUserVideosInfo() method, builds and returns an array of Video object of this class for easier handling
     *
     * @param  int  $cursor  contains the cursor for pagination
     * @param  int  $num_results  max results returned by the API
     * @param  array  $fields  fields to be returned by the API
     * @return Video[] collection of Videos objects
     * @throws Exception If the API returns an error
     */
    public function getUserVideos(int $cursor = 0, int $num_results = 20, array $fields = self::FIELDS_ALL): array
    {
        try {
            $json   = $this->getUserVideosInfo($cursor, $num_results, $fields);
            $videos = [];
            foreach ($json->data->videos as $v) {
                $_v                   = Video::fromJson($v);
                $videos[$_v->getID()] = $_v;
            }
            return [
                'cursor'   => $json->data->cursor,
                'has_more' => $json->data->has_more,
                'videos'   => $videos
            ];
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Helper method to paginate all results from TikTok video pages
     *
     * @param  int  $max_pages  how many pages to fetch. If it's 0, means unlimited
     * @return array collection of Videos objects
     * @throws Exception If the API returns an error
     */
    public function getUserVideoPages(int $max_pages = 0, array $fields = self::FIELDS_ALL): array
    {
        try {
            $videos      = [];
            $cursor      = 0;
            $count_pages = 0;
            while ($vids = $this->getUserVideos($cursor, 20, $fields)) {
                $count_pages++;
                if ($count_pages > $max_pages && $max_pages > 0) {
                    break;
                }
                foreach ($vids['videos'] as $v) {
                    $videos[] = $v;
                }
                if ($vids['has_more']) {
                    $cursor = $vids['cursor'];
                } else {
                    break;
                }
            }
            return $videos;
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * After Calling the TikTok API via the getUserInfo() method, builds and returns the User object of this class for easier handling
     *
     * @param  array  $fields  array containing all the fields you want to retrieve
     * @return User the User object
     * @throws Exception If the API returns an error
     */
    public function getUser(
        array $fields = [
            self::FIELD_U_OPENID, self::FIELD_U_UNION_ID, self::FIELD_U_AVATAR, self::FIELD_U_DISPLAY_NAME
        ],
        bool $get_username = false
    ): User {
        try {
            $json = $this->getUserInfo($fields);
            return User::fromJson($json, $get_username);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Mandatory query to call to retrieve user info before upload. Returns a JSON object that you can parse based on your app logic
     *
     * @return object Json Array with info
     */
    public function getCreatorQueryInfo(): object
    {
        try {
            $res = $this->postWithAuth(self::BASE_CREATOR_QUERY, []);
            return json_decode($res, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Mandatory query to call to retrieve user info before upload. Returns a JSON object that you can parse based on your app logic
     *
     * @return CreatorQuery
     * @throws Exception
     */
    public function getCreatorQuery(): CreatorQuery
    {
        try {
            $res = $this->getCreatorQueryInfo();
            return CreatorQuery::fromJson($res);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Publish a new TikTok Image or Carousel from an array of remote URLs
     *
     * @param  array  $urls  array of URLs each with a photo URL
     * @param  string  $title
     * @param  string  $description
     * @param  string  $privacy_level
     * @param  bool  $disable_comment
     * @param  bool  $auto_add_music
     * @return object json object
     * @throws Exception
     */
    public function publishPhotosFromURL(
        array $urls,
        string $title,
        string $description,
        string $privacy_level = self::PRIVACY_PRIVATE,
        bool $disable_comment = false,
        bool $auto_add_music = false
    ): object {
        if (!self::isValidPrivacyLevel($privacy_level)) {
            throw new RuntimeException('TikTok Invalid Privacy Level Provided: '.$privacy_level.". Must be: ".implode(', ',
                    self::VALID_PRIVACY));
        }
        try {
            $data = [
                'post_info'   => [
                    'title'           => $title,
                    'description'     => $description,
                    'privacy_level'   => $privacy_level,
                    'disable_comment' => $disable_comment,
                    'auto_add_music'  => $auto_add_music
                ],
                'source_info' => [
                    'source'            => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images'      => $urls
                ],
                'post_mode'   => 'DIRECT_POST',
                'media_type'  => 'PHOTO'
            ];
            $res  = $this->postWithAuth(self::BASE_PHOTO_PUBLISH, $data);
            return json_decode($res, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Publish a new TikTok video from a remote URL
     *
     * @param  string  $url
     * @param  string  $title
     * @param  string  $privacy_level
     * @param  bool  $disable_comment
     * @param  bool  $disable_duet
     * @param  bool  $disable_stitch
     * @param  int  $video_cover_timestamp_ms
     * @return object json object
     * @throws Exception
     */
    public function publishVideoFromURL(
        string $url,
        string $title,
        string $privacy_level = self::PRIVACY_PRIVATE,
        bool $disable_comment = false,
        bool $disable_duet = false,
        bool $disable_stitch = false,
        int $video_cover_timestamp_ms = 1000
    ): object {
        if (!self::isValidPrivacyLevel($privacy_level)) {
            throw new RuntimeException('TikTok Invalid Privacy Level Provided: '.$privacy_level.". Must be: ".implode(', ',
                    self::VALID_PRIVACY));
        }
        try {
            $data = [
                'post_info'   => [
                    'title'                    => $title,
                    'privacy_level'            => $privacy_level,
                    'disable_comment'          => $disable_comment,
                    'disable_duet'             => $disable_duet,
                    'disable_stitch'           => $disable_stitch,
                    'video_cover_timestamp_ms' => $video_cover_timestamp_ms
                ],
                'source_info' => [
                    'source'    => 'PULL_FROM_URL',
                    'video_url' => $url
                ]
            ];
            $res  = $this->postWithAuth(self::BASE_POST_PUBLISH, $data);
            return json_decode($res, false, 512, JSON_THROW_ON_ERROR);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Checks the status of a post publish process, using the temporary `publish_id` returned from post/publish/video/init endpoint
     * You can use the VideoFromUrl->publish public method to retrieve a `publish_id`
     *
     * @param  string  $publish_id  returned from the post/publish/video/init endpoint
     * @return PublishStatus
     * @throws Exception
     */
    public function checkPublishStatus(string $publish_id): PublishStatus
    {
        $data = [
            'publish_id' => $publish_id
        ];
        try {
            $res = $this->postWithAuth(self::BASE_PUBLISH_STATUS, $data);
            if (!$res) {
                throw new RuntimeException('TikTok Api Error, invalid returned value '.var_export($res, 1));
            }
            $res = json_decode($res, false, 512, JSON_THROW_ON_ERROR);
            if (!$res) {
                throw new RuntimeException('TikTok Api Error, invalid JSON '.$res);
            }
            return PublishStatus::fromJSON($res);
        } catch (Exception $e) {
            throw new RuntimeException('TikTok Api Error: '.$e->getMessage());
        }
    }

    /**
     * Waits until the upload is completed either with error or success
     *
     * @param  string  $publish_id  returned from the post/publish/video/init endpoint
     * @param  int  $interval
     * @return PublishStatus
     * @throws Exception
     */
    public function waitUntilPublished(string $publish_id, int $interval = 5): PublishStatus
    {
        while (true) {
            sleep($interval);
            $PublishStatus = $this->checkPublishStatus($publish_id);
            if ($PublishStatus->getStatus() === PublishStatus::STATUS_DOWNLOADING || $PublishStatus->getStatus() === PublishStatus::STATUS_UPLOADING) {
                continue;
            }
            return $PublishStatus;
        }
    }

    /**
     * Get protected resources via GET, passing the oAuth token
     *
     * @param  string  $url  The URL to call
     * @return false|string the Response of the call or false
     */
    private function getWithAuth(string $url): false|string
    {
        $headers = [
            'Authorization: Bearer '.$this->getToken()
        ];
        return self::get($url, $headers);
    }

    /**
     * Get protected resources via POST, passing the oAuth token
     *
     * @param  string  $url  The URL to call
     * @param  array  $data  the array containing the POST data in associative format [key: value]
     * @return false|string the Response of the call or false
     * @throws JsonException
     */
    public function postWithAuth(string $url, array $data): false|string
    {
        $headers = [
            'Authorization: Bearer '.$this->getToken()
        ];
        return self::post($url, $data, $headers, true);
    }

    /**
     * Checks if the provided Privacy Level is  valid based on TikTok specs
     *
     * @param  string  $privacy_level
     * @return bool
     */
    public static function isValidPrivacyLevel(string $privacy_level): bool
    {
        if (in_array($privacy_level, self::VALID_PRIVACY)) {
            return true;
        }
        return false;
    }

    /**
     * Basic HTTP wrapper to perform calls to the TikTok Api
     *
     * @param  string  $url  The URL to call
     * @param  array  $headers  optional headers
     * @return false|string the Response of the call or false
     */
    private static function get(string $url, array $headers = []): false|string
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => "GET",
            CURLOPT_HTTPHEADER     => $headers
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return false;
        }

        return $response;
    }

    /**
     * Basic HTTP wrapper to perform POST calls to the TikTok Api
     *
     * @param  string  $url  The URL to call
     * @param  array  $data  the array containing the POST data in associative format [key: value]
     * @param  array  $headers  additional headers to pass to the CURL call
     * @param  bool  $is_json  whether to send this as JSON body
     * @return false|string the Response of the call or false
     * @throws JsonException
     */
    private static function post(string $url, array $data, array $headers = [], bool $is_json = false): false|string
    {
        $curl      = curl_init();
        $headers[] = 'Cache-Control: no-cache';
        $post      = http_build_query($data);
        if ($is_json) {
            $post = json_encode($data, JSON_THROW_ON_ERROR);
            if (!$data) {
                $post = '';
            }
            $headers[] = 'Content-Type: application/json';
        } else {
            $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        }
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING       => "",
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => 1,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => $post
        ]);

        $response = curl_exec($curl);
        $err      = curl_error($curl);

        curl_close($curl);

        if ($err) {
            return false;
        }

        return $response;
    }
}
