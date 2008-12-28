<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Piotr Klaban <makler@man.torun.pl>                           |
// +----------------------------------------------------------------------+
//
// $Id$

/**
 * Exchange rate driver - National Bank of Poland
 *
 * Retrieves daily exchange rates from the National Bank of Poland
 * Snippet from RatesA.html:
 *
 * Current average exchange rates of foreign currencies in zlotys defined in 2
 * para. 1 and 2 of the Resolution No. 51/2002 of the Management Board of the
 * National Bank of Poland on the way of calculating and announcing current
 * exchange rates of foreign currencies, of September 23, 2002 (Dziennik
 * Urzedowy NBP no. 14, item 39 and no. 20, item 51)
 *
 * @link http://www.nbp.pl/Kursy/RatesA.html English HTML version
 * @link http://www.nbp.pl/Kursy/KursyA.html Polish HTML version (with link to XML)
 *
 * @author Piotr Klaban <makler@man.torun.pl>
 * @copyright Copyright 2003 Piotr Klaban
 * @license http://www.php.net/license/2_02.txt PHP License 2.0
 * @package Services_ExchangeRates
 */

/**
 * Include common functions to handle cache and fetch the file from the server
 */
require_once 'Services/ExchangeRates/Rates.php';

/**
 * National Bank of Poland Exchange Rate Driver
 *
 * @package Services_ExchangeRates
 */
class Services_ExchangeRates_Rates_NBP extends Services_ExchangeRates_Rates {

   /**
    * URL of XML feed
    * @var string
    */
    var $feedXMLUrl;
    
   /**
    * URL of HTML page where the XML feed URL is given
    * @var string
    */
    var $feedHTMLUrl = 'http://www.nbp.pl/Kursy/KursyA.html';

   /**
    * Directory in which the XML file is located
    * @var string
    */
    var $feedDir = 'http://www.nbp.pl/Kursy/';

   /**
    * Downloads exchange rates in terms of the PLN from the National Bank of
    * Poland (NBP). This information is updated daily, and is cached by default for 1 hour.
    *
    * Returns a multi-dimensional array containing:
    * 'rates' => associative array of currency codes to exchange rates
    * 'source' => URL of feed
    * 'date' => date feed last updated, pulled from the feed (more reliable than file mod time)
    *
    * @link http://www.nbp.pl/Kursy/RatesA.html English HTML version
    * @link http://www.nbp.pl/Kursy/KursyA.html Polish HTML version (with link to XML)
    *
    * @param int Length of time to cache (in seconds)
    * @return array 
    */
    function retrieve() {

        $return['rates'] = array('PLN' => 1.0);

        // retrieve XML address
        $htmlpage = $this->retrieveFile($this->feedHTMLUrl);

        // Example line is:
        // <div class="file"><a href="xml/a055z020319.xml">powysza tabela w formacie .xml</a></div>
        if (!preg_match('#href="(xml/a\d+z\d+\.xml)"#', $htmlpage, $match))
        {
           Services_ExchangeRates::raiseError("Retrieved url " . $this->feedHTMLUrl . " has no link to XML page", SERVICES_EXCHANGERATES_ERROR_RETRIEVAL_FAILED);
           return false;
        }
        $this->feedXMLUrl = $this->feedDir . $match[1];

        $return['source'] = $this->feedXMLUrl;

        // retrieve the feed from the server or cache
        $root = $this->retrieveXML($this->feedXMLUrl);

        // get down to array of exchange rates
        foreach ($root->children as $rateinfo) {

            if ($rateinfo->name == 'pozycja') {
                list($conversion_rate, $currency_code, $currency_rate) = $this->_extractNodeInformation($rateinfo);

                @$value = $conversion_rate / $currency_rate;
                $return['rates'][$currency_code] = $value;
            } elseif ($rateinfo->name == 'data_publikacji')
            {
                // set date published
                $return['date'] = $rateinfo->content;
            }
        }
        
        return $return; 

    }
    
    /**
     * @todo Todo: a better way to iterate over all children and extract out these!
     * @todo Unit test me please
     */
    function _extractNodeInformation($rateinfo) {
        /*
           <pozycja>
              <nazwa_waluty>korona estońska</nazwa_waluty>
              <przelicznik>1</przelicznik>
              <kod_waluty>EEK</kod_waluty>
              <kurs_sredni>0,2622</kurs_sredni>

           </pozycja>
        */

        // Child node position may vary, unfortunately.

        $conversion_rate = $rateinfo->children[3]->content;
        $currency_code   = $rateinfo->children[5]->content;
        $currency_rate   = strtr($rateinfo->children[7]->content, ',', '.'); //Translate from polish to english style numbers.

        return array($conversion_rate, $currency_code, $currency_rate);
    }
}

?>
