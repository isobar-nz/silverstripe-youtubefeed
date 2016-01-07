<?php

/**
 * Class YouTubeSiteConfigExtension
 *
 * Provides SiteConfig with properties to facilitate YouTube feed retrieval
 */
class YouTubeSiteConfigExtension extends DataExtension
{

    /**
     * @var array
     */
    private static $db = array(
        'YouTubeFeed_AppID' => 'Varchar(255)',
        'YouTubeFeed_AppSecret' => 'Varchar(255)',
        'YouTubeFeed_Token' => 'Varchar(255)',
        'YouTubeFeed_LastSaved' => 'SS_Datetime',
        'YouTubeFeed_AutoUpdate' => 'Boolean',
        'YouTubeFeed_UpdateIntervalUnit' => "Enum('Minutes,Hours,Days', 'Minutes')",
        'YouTubeFeed_UpdateInterval' => 'Int'
    );

    /**
     * @var array
     */
    private static $defaults = array(
        'YouTubeFeed_AutoUpdate' => false,
        'YouTubeFeed_UpdateIntervalUnit' => 'Hours',
        'YouTubeFeed_UpdateInterval' => '6'
    );

    /**
     * If the AppID or AppSecret has changed, remove the access token because it's no longer valid
     */
    public function onBeforeWrite()
    {
        if (isset($this->owner->getChangedFields()['YouTubeFeed_AppID']) || isset($this->owner->getChangedFields()['YouTubeFeed_AppSecret'])) {
            $this->owner->YouTubeFeed_Token = null;
        }
    }

    /**
     * @param FieldList $fields
     */
    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.YouTube',
            new TextField(
                'YouTubeFeed_AppID',
                'Application ID'
            )
        );
        $fields->addFieldToTab('Root.YouTube',
            new TextField(
                'YouTubeFeed_AppSecret',
                'Application Secret'
            )
        );

        if ($this->owner->YouTubeFeed_AppID && $this->owner->YouTubeFeed_AppSecret) {
            $service = new YouTubeFeed();
            if ($service->getIsAuthenticated()) {
                //  We have a valid access token
                $fields->addFieldToTab('Root.YouTube',
                    new LiteralField(
                        'YouTubeTabHeading',
                        "<h2>YouTube has an active connection.</h2>"
                    )
                );
                $fields->addFieldToTab('Root.YouTube',
                    new CheckboxField(
                        'YouTubeFeed_AutoUpdate',
                        'Automatically fetch YouTube video information'
                    )
                );

                if ($this->owner->YouTubeFeed_AutoUpdate) {
                    $fields->addFieldToTab('Root.YouTube',
                        new NumericField(
                            'YouTubeFeed_UpdateInterval',
                            'Update interval'
                        )
                    );
                    $fields->addFieldToTab('Root.YouTube',
                        $updateIntervalUnitsField = new DropdownField(
                            'YouTubeFeed_UpdateIntervalUnit',
                            '&nbsp;',
                            singleton('SiteConfig')->dbObject('YouTubeFeed_UpdateIntervalUnit')->enumValues()
                        )
                    );
                    $updateIntervalUnitsField->setRightTitle('This time period defines the minimum length of time between each request to YouTube to check for new or updated videos.');
                }
            } else {
                // YouTube isn't connected -- provide auth link
                $serviceURL = $service->getAuthURL();
                $fields->addFieldToTab('Root.YouTube',
                    new LiteralField('area', '<a href="' . $serviceURL . '" name="action_AuthenticateYouTube" value="Authenticate YouTube Application" class="action ss-ui-button ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only" id="Form_EditForm_action_AuthenticateYouTube" role="button" aria-disabled="false"><span class="ui-button-text">
		Authenticate YouTube Application
	</span></a>')
                );
            }
        }
    }
}
