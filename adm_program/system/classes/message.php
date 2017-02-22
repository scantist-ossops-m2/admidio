<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class Message
 * @brief Simple presentation of messages to the user
 *
 * This class creates a new html page with a simple headline and a message. It's
 * designed to easily integrate this class into your code. An object @b $gMessage
 * of this class is created in the common.php. You can set a url that should be
 * open after user confirmed the message or you can show a question with two
 * default buttons yes and no. There is also an option to automatically leave the
 * message after some time.
 * @par Examples
 * @code // show a message with a back button, the object $gMessage is created in common.php
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message and set a link to a page that should be shown after user click ok
 * $gMessage->setForwardUrl('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID'));
 *
 * // show a message with yes and no button and set a link to a page that should be shown after user click yes
 * $gMessage->setForwardYesNo('https://www.example.com/mypage.php');
 * $gMessage->show($gL10n->get('SYS_MESSAGE_TEXT_ID')); @endcode
 */
class Message
{
    private $inline;            // wird ermittelt, ob bereits eine Ausgabe an den Browser erfolgt ist
    private $forwardUrl;        // Url auf die durch den Weiter-Button verwiesen wird
    private $timer;             // Anzahl ms bis automatisch zu forwardUrl weitergeleitet wird
    private $includeThemeBody;  ///< Includes the header and body of the theme to the message. This will be included as default.
    private $showTextOnly;      ///< If set to true then no html elements will be shown, only the pure text message.
    private $showHtmlTextOnly;  ///< If set to true then only the message with their html elements will be shown.

    private $showButtons;       // Buttons werden angezeigt
    private $showYesNoButtons;  // Anstelle von Weiter werden Ja/Nein-Buttons angezeigt
    private $modalWindowMode;   ///< If this is set to true than the message will be show with html of the bootstrap modal window

    /**
     * Constructor that initialize the class member parameters
     */
    public function __construct()
    {
        $this->inline           = false;
        $this->forwardUrl       = '';
        $this->timer            = 0;
        $this->includeThemeBody = true;
        $this->showTextOnly     = false;
        $this->showHtmlTextOnly = false;

        $this->showButtons      = true;
        $this->showYesNoButtons = false;
        $this->modalWindowMode  = false;
    }

    /**
     * No button will be shown in the message window.
     */
    public function hideButtons()
    {
        $this->showButtons = false;
    }

    /**
     * If this is set to true than the message will be show with html of the bootstrap modal window.
     */
    public function showInModaleWindow()
    {
        $this->modalWindowMode = true;
        $this->inline = true;
    }

    /**
     * Set a URL to which the user should be directed if he confirmed the message.
     * It's possible to set a timer after that the page of the url will be
     * automatically displayed without user interaction.
     * @param string $url   The full url to which the user should be directed.
     * @param int    $timer Optional a timer in millisecond after the user will be automatically redirected to the $url.
     */
    public function setForwardUrl($url, $timer = 0)
    {
        $this->forwardUrl = $url;
        $this->timer      = $timer;
    }

    /**
     * Add two buttons with the labels @b yes and @b no to the message. If the user choose yes
     * he will be redirected to the $url. If he chooses no he will be directed back to the previous page.
     * @param string $url The full url to which the user should be directed if he chooses @b yes.
     */
    public function setForwardYesNo($url)
    {
        $this->forwardUrl       = $url;
        $this->showYesNoButtons = true;
    }

    /**
     * Create a html page if necessary and show the message with the configured buttons.
     * @param string $content  The message text that should be shown. The content could have html.
     * @param string $headline Optional a headline for the message. Default will be SYS_NOTE.
     */
    public function show($content, $headline = '')
    {
        // noetig, da dies bei den includes benoetigt wird
        global $gDb, $gL10n, $page;

        $html = '';

        // first perform a rollback in database if there is an open transaction
        $gDb->rollback();

        // Ueberschrift setzen, falls diese vorher nicht explizit gesetzt wurde
        if($headline === '')
        {
            $headline = $gL10n->get('SYS_NOTE');
        }

        // Variablen angeben
        if(!$this->inline)
        {
            // nur pruefen, wenn vorher nicht schon auf true gesetzt wurde
            $this->inline = headers_sent();
        }

        if(!$this->inline)
        {
            // create html page object
            $page = new HtmlPage($headline);
            $page->hideMenu();

            if(!$this->includeThemeBody)
            {
                // don't show custom html of the current theme
                $page->hideThemeHtml();
            }

            // forward to next page after x seconds
            if ($this->timer > 0)
            {
                $page->addJavascript('
                    setTimeout(function() {
                        window.location.href = "'. $this->forwardUrl. '";
                    }, '. $this->timer. ');'
                );
            }
        }
        elseif(!$this->modalWindowMode)
        {
            $html .= '<h1>'.$headline.'</h1>';
        }

        // create html for buttons
        $htmlButtons = '';

        if($this->showButtons)
        {
            if($this->forwardUrl !== '')
            {
                if($this->showYesNoButtons)
                {
                    $htmlButtons .= '
                        <button id="admButtonYes" class="btn" type="button" onclick="self.location.href = \"'. $this->forwardUrl. '\"">
                            <img src="'. THEME_URL. '/icons/ok.png" alt="'.$gL10n->get('SYS_YES').'" />
                            &nbsp;&nbsp;'.$gL10n->get('SYS_YES').'&nbsp;&nbsp;&nbsp;
                        </button>
                        <button id="admButtonNo" class="btn" type="button" onclick="history.back()">
                            <img src="'. THEME_URL. '/icons/error.png" alt="'.$gL10n->get('SYS_NO').'" />
                            &nbsp;'.$gL10n->get('SYS_NO').'
                        </button>';
                }
                else
                {
                    // Wenn weitergeleitet wird, dann auch immer einen Weiter-Button anzeigen
                    $htmlButtons .= '
                        <a class="btn" href="'. $this->forwardUrl. '">'.$gL10n->get('SYS_NEXT').'
                            <img src="'. THEME_URL. '/icons/forward.png" alt="'.$gL10n->get('SYS_NEXT').'"
                                title="'.$gL10n->get('SYS_NEXT').'" />
                        </a>';
                }
            }
            else
            {
                // Wenn nicht weitergeleitet wird, dann immer einen Zurueck-Button anzeigen
                // bzw. ggf. einen Fenster-Schließen-Button
                if(!$this->modalWindowMode)
                {
                    $htmlButtons .= '
                        <a class="btn" href="javascript:void(0)" onclick="history.back()">
                            <img src="'.THEME_URL.'/icons/back.png" alt="'.$gL10n->get('SYS_BACK').'"
                                title="'.$gL10n->get('SYS_BACK').'" />'.
                            $gL10n->get('SYS_BACK').
                        '</a>';
                }
            }
        }

        if($this->modalWindowMode)
        {
            $html .= '
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h4 class="modal-title">'.$headline.'</h4>
                </div>
                <div class="modal-body">'.$content.'</div>
                <div class="modal-footer">'.$htmlButtons.'</div>';
        }
        else
        {
            $html .= '
                <div class="message">
                    <p class="lead">'. $content.'</p>
                    '.$htmlButtons.'
                </div>';
        }

        if($this->showTextOnly)
        {
            // show the pure message text without any html
            echo strip_tags($content);
        }
        elseif($this->showHtmlTextOnly)
        {
            // show the pure message text with their html
            echo $content;
        }
        elseif($this->inline)
        {
            // show the message in html but without the theme specific header and body
            echo $html;
        }
        else
        {
            // show a Admidio html page with complete theme header and body
            $page->addHtml($html);
            $page->show();
        }
        exit();
    }

    /**
     * If this will be set then only the text message will be shown.
     * If this message contains html elements then these will also be shown in the output.
     * @param bool $showText If set to true than only the message text with their html elements will be shown.
     */
    public function showHtmlTextOnly($showText)
    {
        $this->showHtmlTextOnly = $showText;
    }

    /**
     * If set no theme files will be integrated in the page.
     * This setting is useful if the message should be loaded in a small window.
     * @param bool $showTheme If set to true than theme body and header will be shown. Otherwise this will be hidden.
     */
    public function showThemeBody($showTheme)
    {
        $this->includeThemeBody = $showTheme;
    }

    /**
     * If this will be set then no html elements will be shown in the output,
     * only pure text. This is useful if you have a script that is used in ajax mode.
     * @param bool $showText If set to true than only the message text without any html will be shown.
     */
    public function showTextOnly($showText)
    {
        $this->showTextOnly = $showText;
    }
}
