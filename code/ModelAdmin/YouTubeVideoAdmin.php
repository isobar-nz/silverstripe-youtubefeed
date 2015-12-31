<?php

/**
 * Class YouTubeVideoAdmin
 */
class YouTubeVideoAdmin extends ModelAdmin
{
    /**
     * @var array
     */
    private static $managed_models = array(
        'YouTubeVideo'
    );

    /**
     * @var string
     */
    private static $url_segment = 'youtube-videos';

    /**
     * @var string
     */
    private static $menu_title = 'YouTube Videos';
}
