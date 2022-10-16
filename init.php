<?php
class mstra extends Plugin
{

    /* @var PluginHost $host */
    private $host;

    public function about()
    {
        return array(1.0,
            "Transalte Title to Chinese Simple",
            "zy55769068");
    }

    public function flags()
    {
        return array("needs_curl" => true);
    }

    public function save()
    {
        $this->host->set($this, "mstratoken", $_POST["mstratoken"]);

        echo __("API server address saved.");
    }

    public function init($host)
    {
        $this->host = $host;

        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            user_error("Translate requires PHP 7.0", E_USER_WARNING);
            return;
        }

        $host->add_hook($host::HOOK_ARTICLE_FILTER, $this);
        $host->add_hook($host::HOOK_PREFS_TAB, $this);
        $host->add_hook($host::HOOK_PREFS_EDIT_FEED, $this);
        $host->add_hook($host::HOOK_PREFS_SAVE_FEED, $this);
        $host->add_hook($host::HOOK_ARTICLE_BUTTON, $this);

        $host->add_filter_action($this, "Translate", __("Translate"));
    }

    public function get_js()
    {
        return file_get_contents(__DIR__ . "/init.js");
    }

    public function hook_article_button($line)
    {
        return "<i class='material-icons'
			style='cursor : pointer' onclick='Plugins.mstra.convert(" . $line["id"] . ")'
			title='" . __('Convert via mstra') . "'>translate</i>";
    }

    public function hook_prefs_tab($args)
    {
        if ($args != "prefFeeds") {
            return;
        }

        print "<div dojoType='dijit.layout.AccordionPane'
			title=\"<i class='material-icons'>extension</i> " . __('mstra settings (mstra)') . "\">";

        if (version_compare(PHP_VERSION, '7.0.0', '<')) {
            print_error("This plugin requires PHP 7.0.");
        } else {
            print_notice("Enable the plugin for specific feeds in the feed editor.");

            print "<form dojoType='dijit.form.Form'>";

            print "<script type='dojo/method' event='onSubmit' args='evt'>
                evt.preventDefault();
                if (this.validate()) {
                xhr.post(\"backend.php\", this.getValues(), (reply) => {
                            Notify.info(reply);
                        })
                }
                </script>";

            print \Controls\pluginhandler_tags($this, "save");

            $mstratoken = $this->host->get($this, "mstratoken");

            print "<input dojoType='dijit.form.ValidationTextBox' required='1' name='mstratoken' value='" . $mstratoken . "'/>";

            print "&nbsp;<label for='mstratoken'>" . __("mstratoken.") . "</label>";

            print "<button dojoType=\"dijit.form.Button\" type=\"submit\" class=\"alt-primary\">" . __('Save') . "</button>";

            print "</form>";

            $enabled_feeds = $this->host->get($this, "enabled_feeds");
            if (!is_array($enabled_feeds)) {
                $enabled_feeds = array();
            }

            $enabled_feeds = $this->filter_unknown_feeds($enabled_feeds);
            $this->host->set($this, "enabled_feeds", $enabled_feeds);

            if (count($enabled_feeds) > 0) {
                print "<h3>" . __("Currently enabled for (click to edit):") . "</h3>";

                print "<ul class='panel panel-scrollable list list-unstyled'>";
                foreach ($enabled_feeds as $f) {
                    print "<li>" .
                    "<i class='material-icons'>rss_feed</i> <a href='#'
						onclick='CommonDialogs.editFeed($f)'>" .
                    Feeds::_get_title($f) . "</a></li>";

                }
                print "</ul>";
            }
        }

        print "</div>";
    }

    public function hook_prefs_edit_feed($feed_id)
    {
        print "<header>" . __("mstra") . "</header>";
        print "<section>";

        $enabled_feeds = $this->host->get($this, "enabled_feeds");
        if (!is_array($enabled_feeds)) {
            $enabled_feeds = array();
        }

        $key = array_search($feed_id, $enabled_feeds);
        $checked = $key !== false ? "checked" : "";

        print "<fieldset>";

        print "<label class='checkbox'><input dojoType='dijit.form.CheckBox' type='checkbox' id='mstra_enabled'
				name='mstra_enabled' $checked>&nbsp;" . __('Enable mstra') . "</label>";

        print "</fieldset>";

        print "</section>";
    }

    public function hook_prefs_save_feed($feed_id)
    {
        $enabled_feeds = $this->host->get($this, "enabled_feeds");
        if (!is_array($enabled_feeds)) {
            $enabled_feeds = array();
        }

        $enable = checkbox_to_sql_bool($_POST["mstra_enabled"]);
        $key = array_search($feed_id, $enabled_feeds);

        if ($enable) {
            if ($key === false) {
                array_push($enabled_feeds, $feed_id);
            }
        } else {
            if ($key !== false) {
                unset($enabled_feeds[$key]);
            }
        }

        $this->host->set($this, "enabled_feeds", $enabled_feeds);
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function hook_article_filter_action($article, $action)
    {
        return $this->process_article($article);
    }

    public function send_request($contentvalue)
    {
        $curl = curl_init();
        $request_body = '[{ "Text": "' . $contentvalue . '" }]';
        $mstratoken = $this->host->get($this, "mstratoken");

        $headers = array();
        $headers[] = 'Ocp-Apim-Subscription-Key:' .$mstratoken;
        $headers[] = 'Ocp-Apim-Subscription-Region: japaneast';
        $headers[] = 'Content-Type: application/json; charset=UTF-8';

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.cognitive.microsofttranslator.com/translate?api-version=3.0&from=en&to=zh-Hans&textType=html',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $request_body,
            CURLOPT_HTTPHEADER => $headers,
        ));

        $response = curl_exec($curl);

        curl_close($curl);
    $output = json_decode(curl_exec($curl), true)[0]["translations"][0]["text"];    
        curl_close($curl);

        return $output;
    }

    public function process_article($article)
    {
        $output = $this->send_request($article["title"]);
        $article["title"] = $output;
        return $article;
    }

    public function hook_article_filter($article)
    {
        $enabled_feeds = $this->host->get($this, "enabled_feeds");
        if (!is_array($enabled_feeds)) {
            return $article;
        }

        $key = array_search($article["feed"]["id"], $enabled_feeds);
        if ($key === false) {
            return $article;
        }

        return $this->process_article($article);
    }

    public function api_version()
    {
        return 2;
    }

    private function filter_unknown_feeds($enabled_feeds)
    {
        $tmp = array();

        foreach ($enabled_feeds as $feed) {
            $sth = $this->pdo->prepare("SELECT id FROM ttrss_feeds WHERE id = ? AND owner_uid = ?");
            $sth->execute([$feed, $_SESSION['uid']]);

            if ($row = $sth->fetch()) {
                array_push($tmp, $feed);
            }
        }

        return $tmp;
    }

    public function convert()
    {
        $article_id = (int) $_REQUEST["id"];

        $sth = $this->pdo->prepare("SELECT title, content FROM ttrss_entries WHERE id = ?");
        $sth->execute([$article_id]);

        $result = [];
        if ($row = $sth->fetch()) {
            $output = $this->send_request($row["title"]);

            $result["title"] = $output;
            $result["content"] = $row["content"];

        }
        print json_encode($result);
    }
}
