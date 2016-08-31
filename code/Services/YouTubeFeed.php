<?php

/**
 * Class YouTubeFeed
 *
 * Provides YouTube user profile access
 */
class YouTubeFeed extends Controller
{

    /**
     * @var Google_Client
     */
    private $client;

    /**
     * @var Google_Service_YouTube
     */
    private $service;

    /**
     * @var string
     */
    private $stateSessionIdentifier = 'YouTubeFeed_State';

    /**
     * @var array
     */
    private static $allowed_actions = array(
        'authenticate'
    );

    /**
     * Instantiate the Google API and feed provided config values
     * We require a long-lived access token
     */
    public function __construct()
    {
        parent::__construct();

        $siteConfig = SiteConfig::current_site_config();
        $appID = $siteConfig->YouTubeFeed_AppID;
        $appSecret = $siteConfig->YouTubeFeed_AppSecret;

        $this->client = new Google_Client();
        $this->client->setScopes('https://www.googleapis.com/auth/youtube');
        $this->client->setAccessType('offline');
        $this->client->setApprovalPrompt('force');

        if (!Director::is_cli()) {
            $this->client->setRedirectUri(Director::absoluteBaseURL() . 'youtube/authenticate');
        }

        if ($appID && $appSecret) {
            $this->client->setClientId($appID);
            $this->client->setClientSecret($appSecret);
            $this->service = new Google_Service_YouTube($this->client);

            if ($accessToken = $this->getConfigToken()) {
                $this->client->setAccessToken($accessToken);
            }
        }
    }

    /**
     * Provides an endpoint to complete YouTube OAuth
     *
     * @return SS_HTTPResponse|string
     */
    public function authenticate()
    {
        $response = $this->getResponse();

        if ($code = $this->getRequest()->getVar('code')) {
            if (strval(Session::get($this->stateSessionIdentifier)) !== strval($this->getRequest()->getVar('state'))) {
                $response->setStatusCode('400');
                $response->setBody('The state did not match');
            } else {
                if ($this->client) {
                    $this->client->authenticate($code);
                    $token = $this->client->getAccessToken();
                    $this->setConfigToken($token);
                    return $this->redirect(Director::absoluteBaseURL() . 'admin/settings');
                } else {
                    $response->setStatusCode(400);
                    $response->setBody('Google account not connected');
                }
            }
        } else {
            $response->setStatusCode(400);
            $response->setBody('Bad request');
        }

        return $response;
    }

    /**
     * Returns a URL the user can visit to grant us permission to access their feed
     *
     * @return string
     */
    public function getAuthURL()
    {
        $state = mt_rand();
        $this->client->setState($state);
        Session::set($this->stateSessionIdentifier, $state);
        return $this->client->createAuthUrl();
    }

    /**
     * Returns true if the user has a valid access token
     *
     * @return string
     */
    public function getIsAuthenticated()
    {
        return $this->client->getAccessToken();
    }

    /**
     * Checks the connected YouTube account for new uploads, and calls processVideo() on each one.
     * Returns an array containing up to $limit YouTubeVideo objects
     *
     * @param $limit Int number of results to retrieve
     * @return array
     */
    public function getRecentUploads($limit = 50)
    {
        if ($this->getIsAuthenticated()) {
            try {
                // Call the channels.list method to retrieve information about the
                // currently authenticated user's channel.
                $channelsResponse = $this->service->channels->listChannels('contentDetails', array(
                    'mine' => 'true',
                ));
                $uploads = array();
                foreach ($channelsResponse['items'] as $channel) {
                    // Extract the unique playlist ID that identifies the list of videos
                    // uploaded to the channel, and then call the playlistItems.list method
                    // to retrieve that list.
                    $uploadsListId = $channel['contentDetails']['relatedPlaylists']['uploads'];
                    $playlistItemsResponse = $this->service->playlistItems->listPlaylistItems('snippet', array(
                        'playlistId' => $uploadsListId,
                        'maxResults' => $limit
                    ));

                    foreach ($playlistItemsResponse['items'] as $playlistItem) {
                        $videoObject = $this->processVideo($playlistItem);
                        array_push($uploads, $videoObject);
                    }
                }
            } catch (Google_Service_Exception $e) {
                error_log(sprintf('<p>A service error occurred: <code>%s</code></p>',
                    htmlspecialchars($e->getMessage())));
            } catch (Google_Exception $e) {
                error_log(sprintf('<p>An client error occurred: <code>%s</code></p>',
                    htmlspecialchars($e->getMessage())));
            }

            $token = $this->client->getAccessToken();
            $this->setConfigToken($token);

            return isset($uploads) ? $uploads : false;
        }

        return false;
    }


    /**
     * Saves a Google_Service_YouTube_PlaylistItem into YouTubeVideo
     * Overwrites an existing object, or creates a new one.
     * Returns the YouTubeVideo DataObject.
     *
     * @param $video
     * @return YouTubeVideo
     */
    protected function processVideo($video)
    {
        $snippet = $video['snippet'];
        $privacyStatus = $video['status']['privacyStatus'];
        
        if ($privacyStatus == 'public') {

            $videoFields = array();

            // Map response data to columns in our YouTubeVideo table
            $videoFields['VideoID'] = $snippet['resourceId']['videoId'];
            $videoFields['Description'] = $snippet['description'];
            $videoFields['Published'] = strtotime($snippet['publishedAt']);
            $videoFields['Title'] = $snippet['title'];
            $videoFields['ChannelTitle'] = $snippet['channelTitle'];
            $videoFields['ChannelID'] = $snippet['channelId'];
            $videoFields['PlaylistID'] = $snippet['playlistId'];
            $videoFields['PlaylistPosition'] = $snippet['position'];

            // Get the highest res thumbnail available
            if (isset($snippet['thumbnails']['maxres'])) {
                $videoFields['ThumbnailURL'] = $snippet['thumbnails']['maxres']['url'];
            } elseif (isset($snippet['thumbnails']['standard'])) {
                $videoFields['ThumbnailURL'] = $snippet['thumbnails']['standard']['url'];
            } elseif (isset($snippet['thumbnails']['high'])) {
                $videoFields['ThumbnailURL'] = $snippet['thumbnails']['high']['url'];
            } elseif (isset($snippet['thumbnails']['medium'])) {
                $videoFields['ThumbnailURL'] = $snippet['thumbnails']['medium']['url'];
            } elseif (isset($snippet['thumbnails']['default'])) {
                $videoFields['ThumbnailURL'] = $snippet['thumbnails']['default']['url'];
            }

            // Try retrieve existing YouTubeVideo by Youtube Video ID, create if it doesn't exist
            $videoObject = YouTubeVideo::getExisting($videoFields['VideoID']);

            if (!$videoObject) {
                $videoObject = new YouTubeVideo();
                $newYouTubeVideo = true;
            }

            $videoObject->update($videoFields);
            $videoObject->write();

            if (isset($newYouTubeVideo)) {
                // Allow decoration of YouTubeVideo with onAfterCreate(YouTubeVideo $videoObject) method
                $this->extend('onAfterCreate', $videoObject);
            }

            return $videoObject;
        
        } else {
            
            $videoObject = YouTubeVideo::getExisting($snippet['resourceId']['videoId']);
            if($videoObject && $videoObject->exists()) {
                $videoObject->delete();
            }
            
        }
        
        return null;
    }

    /**
     * Returns the access token from SiteConfig
     *
     * @return mixed
     */
    protected function getConfigToken()
    {
        return SiteConfig::current_site_config()->YouTubeFeed_Token;
    }

    /**
     * Saves the access token into SiteConfig
     *
     * @return void
     */
    protected function setConfigToken($token)
    {
        $siteConfig = SiteConfig::current_site_config();
        $siteConfig->YouTubeFeed_Token = $token;
        $siteConfig->write();
    }

    /**
     * Returns the SS_Datetime a YouTubeVideo was last retrieved from the external service
     *
     * @return SS_Datetime
     */
    protected function getTimeLastSaved()
    {
        return SiteConfig::current_site_config()->YouTubeFeed_LastSaved;
    }

    /**
     * Checks if it's time to do a video update, or performs one anyway if $force is true
     *
     * @param bool $force Force auto update, disregards 'YouTubeFeed_AutoUpdate' property
     * @throws ValidationException
     * @throws null
     */
    public function doAutoUpdate($force = false)
    {
        $siteConfig = SiteConfig::current_site_config();

        if ($force || $siteConfig->YouTubeFeed_AutoUpdate) {
            $lastUpdated = $siteConfig->YouTubeFeed_LastSaved;
            $nextUpdateInterval = $siteConfig->YouTubeFeed_UpdateInterval;
            $nextUpdateIntervalUnit = $siteConfig->YouTubeFeed_UpdateIntervalUnit;

            if ($lastUpdated) {
                // Assemble the time another update became required as per SiteConfig options
                // YouTubeFeed_NextUpdateInterval & ..Unit
                $minimumUpdateTime = strtotime($lastUpdated . ' +' . $nextUpdateInterval . ' ' . $nextUpdateIntervalUnit);
            }

            // If we haven't auto-updated before (fresh install), or an update is due, do update
            if ($force || !isset($minimumUpdateTime) || $minimumUpdateTime < time()) {
                $this->getRecentUploads();

                // Save the time the update was performed
                $siteConfig->YouTubeFeed_LastSaved = SS_Datetime::now()->value;
                $siteConfig->write();
            }
        }
    }
}
