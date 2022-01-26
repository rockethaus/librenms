source: Extensions/metrics/InfluxDB2.md
path: blob/master/doc/

# Enabling support for InfluxDB2/Telegraf

Support for InfluxDB2 was built upon the pre-existing "alpha level"
InfluxDB v1 support.  Data is exported to InfluxDB2 successfully in a
reasonably performant manner allowing you to use the metrics natively
in InfluxDB2 or through other 

# Requirements

- InfluxDB 1.8+ or 2+
- Grafana or other dashboarding application
- PHP 5.5 for InfluxDB-PHP

The setup of the above is completely out of scope here and we aren't
really able to provide any help with this side of things.  Using
InfluxDB2 and Grafana is fairly popular, there are lots of helpful
guide available on the internet.

# InfluxDB Cloud Support

[InfluxDB Cloud](https://www.influxdata.com/products/influxdb-cloud/) is
Influxdata's hosted Influx solution and is an easy way to get started
with InfluxDB.  This datastore extension fully supports InfluxDB Cloud,
simply configure the bucket, org, url and token to the values provided
when you setup your account @ InfluxDB Cloud.

# Telegraf UDP Support

Sending UDP data to Influx is done through [Telegraf](https://www.influxdata.com/time-series-platform/telegraf/).  
Telegraf is lightweight, plugin-driven server agent for collecting AND sending
metrics and events from/to databases, systems and sensors.  Telegraf
is malleable to the point where data sent to Telegraf could be sent to
pretty much any other system and could replace all the datastore extensions
in LibreNMS.  Telegraf support has been implemented here as an extension
of InfluxDB2 but it could be used to export to other systems that Telegraf
supports.  Experiment at your own risk!

# Additional Tags

There are two configuration settings that allow you to add static tags
to all datapoints sent to InfluxDB2.  These are indexed tags to allow 
for easy segregation of the LibreNMS data or additional classification.

# What you don't get

- Pre-defined graphs or dashboards.  Once the metrics start to flow, you need
  build your own graphs within InfluxDB2 or Grafana.
- Support for InfluxDB2 or Grafana, we would highly recommend that you
  have some level of experience with these.

RRD will continue to function as normal so LibreNMS itself should
continue to function as normal.

# Configuration

```php
$config['influxdb2']['enable'] = true;
$config['influxdb2']['transport'] = 'http'; # http covers both http/https, other option: udp
$config['influxdb2']['url'] = 'http://localhost:8086'; #https URLs are supported
$config['influxdb2']['udpHost'] = '127.0.0.1';
$config['influxdb2']['udpPort'] = '8094';
$config['influxdb2']['bucket'] = ''; # This will need to be created in InfluxDB2
$config['influxdb2']['org'] = ''; # This will need to be specified in InfluxDB2
$config['influxdb2']['token'] = ''; # This will need to be created in InfluxDB2
$config['influxdb2']['timeout'] = 0; # Optional
$config['influxdb2']['verifySSL'] = false; # Optional
$config['influxdb2']['tag01Name'] = ''; # Optional
$config['influxdb2']['tag01Value'] = ''; # Optional
$config['influxdb2']['tag02Name'] = ''; # Optional
$config['influxdb2']['tag02Value'] = ''; # Optional
```

InfluxDB2 expects a minimum of URL, Organization, Token and Bucket.

The same data then stored within rrd will be sent to InfluxDB2 and
recorded. You can then create graphs within Grafana to display the
information you need.
