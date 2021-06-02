<?php
/**
 * InfluxDB2.php
 *
 * -Description-
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * @link       https://www.librenms.org
 * @copyright  2020 Tony Murray
 * @copyright  2014 Neil Lathwood <https://github.com/laf/ http://www.lathwood.co.uk/fa>
 * @author     Tony Murray <murraytony@gmail.com>
 */

namespace LibreNMS\Data\Store;

use InfluxDB2\Client;
use LibreNMS\Config;
use LibreNMS\Data\Measure\Measurement;
use Log;

class InfluxDB2 extends BaseDatastore
{
    /** @var \InfluxDB2\Database */
    private $connection;

    public function __construct(\InfluxDB2\Database $influx)
    {
        parent::__construct();
        $this->connection = $influx;

        #RH Need to figure out where this is called from and likely eliminate it, you can't get a token unless you already have a bucket
        #KP This is called automatically by the "new InfluxDB2" line inside of createFromConfig()
        // check if the connection is healthy
        try {
            $influx->health();
        } catch (\Exception $e) {
            Log::warning('InfluxDB2: Health check failed.');
        }
    }

    public function getName()
    {
        return 'InfluxDB2';
    }

    public static function isEnabled()
    {
        return Config::get('influxdb2.enable', false);
    }

    /**
     * Datastore-independent function which should be used for all polled metrics.
     *
     * RRD Tags:
     *   rrd_def     RrdDefinition
     *   rrd_name    array|string: the rrd filename, will be processed with rrd_name()
     *   rrd_oldname array|string: old rrd filename to rename, will be processed with rrd_name()
     *   rrd_step             int: rrd step, defaults to 300
     *
     * @param array $device
     * @param string $measurement Name of this measurement
     * @param array $tags tags for the data (or to control rrdtool)
     * @param array|mixed $fields The data to update in an associative array, the order must be consistent with rrd_def,
     *                            single values are allowed and will be paired with $measurement
     */
    public function put($device, $measurement, $tags, $fields)
    {
        $stat = Measurement::start('write');
        $tmp_fields = [];
        $tmp_tags['hostname'] = $device['hostname'];
        foreach ($tags as $k => $v) {
            if (empty($v)) {
                $v = '_blank_';
            }
            $tmp_tags[$k] = $v;
        }
        foreach ($fields as $k => $v) {
            if ($k == 'time') {
                $k = 'rtime';
            }

            if (($value = $this->forceType($v)) !== null) {
                $tmp_fields[$k] = $value;
            }
        }

        if (empty($tmp_fields)) {
            Log::warning('All fields empty, skipping update', ['orig_fields' => $fields]);

            return;
        }

        Log::debug('InfluxDB2 data: ', [
            'measurement' => $measurement,
            'tags' => $tmp_tags,
            'fields' => $tmp_fields,
        ]);

        try {
            $point = InfluxDB2\Point::measurement($measurement)
                    ->appendTags($tmp_tags)
                    ->appendFields($tmp_fields);

            $this->writeApi->write($point);
            #RH - Not sure what the next line does.  I'm guessing it writes to the Libre base datastore...
            $this->recordStatistic($stat->end());
        } catch (\InfluxDB2\Exception $e) {
            #RH - this error catch needs to be verfied for v2
            Log::error('InfluxDB2 exception: ' . $e->getMessage());
            Log::debug($e->getTraceAsString());
        }
    }

    /**
     * Create a new client and select the database
     *
     * @return \InfluxDB2\Database
     */
    public static function createFromConfig()
    {
        $url = Config::get('influxdb2.url', 'http://localhost:8086');
        $transport = Config::get('influxdb2.transport', 'http');
        $token = Config::get('influxdb2.token', '');
        $bucket = Config::get('influxdb2.bucket', 'librenms');
        $org = Config::get('influxdb2.org', '');
        $timeout = Config::get('influxdb2.timeout', 0);
        $verify_ssl = Config::get('influxdb2.verifySSL', false);
        $tag01_name = Config::get('influxdb2.tag01Name', '');
        $tag01_value = Config::get('influxdb2.tag01Value', '');
        $tag02_name = Config::get('influxdb2.tag02Name', '');
        $tag02_value = Config::get('influxdb2.tag02Value', '');
        $udp_host = Config::get('influxdb2.udpHost', '');
        $udp_port = Config::get('influxdb2.udpPort', '');

        

        if ($transport == 'udp') {

            $client = new InfluxDB2\Client([
                "udpHost" => $udp_host,
                "udpPort" => $udp_port,
                "token" => $token,
                "bucket" => $bucket,
                "org" => $org,
                "precision" => WritePrecision::S
            ]);
            
            $writeApi = $client->createUdpWriter();

            #RH - I'm not sure if the below is going to work.  Can the UDP writer support the point format?
            # If it can't, we're going to need to put a bunch more logic in the data writer
            if(!empty($tag01_name) && !empty($tag01_value)) $writeApi->pointSettings->addDefaultTag($tag01_name, $tag01_value);
            if(!empty($tag02_name) && !empty($tag02_value)) $writeApi->pointSettings->addDefaultTag($tag02_name, $tag02_value);

        } else {

            $client = new InfluxDB2\Client([
                "url" => $url,
                "token" => $token,
                "bucket" => $bucket,
                "org" => $org,
                "precision" => WritePrecision::S,
                "timeout" => $timeout,
                "verifySSL" => $verify_ssl
            ]);

            $writeApi = $client->createWriteApi();
            if(!empty($tag01_name) && !empty($tag01_value)) $writeApi->pointSettings->addDefaultTag($tag01_name, $tag01_value);
            if(!empty($tag02_name) && !empty($tag02_value)) $writeApi->pointSettings->addDefaultTag($tag02_name, $tag02_value);
        }

        #RH - Not entirely sure what the return here does.  Is it needed?  I'm guessing that this was essentially a connection verification, 
        # maybe we just need the health check and a catch if it fails
        return $client->selectDB($db);
    }

    private function forceType($data)
    {
        /*
         * It is not trivial to detect if something is a float or an integer, and
         * therefore may cause breakages on inserts.
         * Just setting every number to a float gets around this, but may introduce
         * inefficiencies.
         */

        if (is_numeric($data)) {
            return floatval($data);
        }

        return $data === 'U' ? null : $data;
    }

    /**
     * Checks if the datastore wants rrdtags to be sent when issuing put()
     *
     * @return bool
     */
    public function wantsRrdTags()
    {
        return false;
    }
}
