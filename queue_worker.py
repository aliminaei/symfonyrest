#!/usr/bin/env python
import sys, getopt
import pika
import time
import requests
import json
from datetime import datetime
from time import sleep


class Queue_Worker:
    API_USERNAME = ""
    API_TOKEN = ""

    def callback(self, ch, method, properties, body):
        package_name = body

        repo_url = self.get_repo_url(package_name)

        if repo_url == "":
            print "Error parsing package: %s"%package_name
            ch.basic_ack(delivery_tag = method.delivery_tag)
            return

        #extracting repo name
        repo_name =  repo_url.replace('https://github.com/', '').replace("http://github.com/", "")
        contributors_url = "https://api.github.com/repos/%s/contributors"%repo_name

        req = requests.get(contributors_url, auth=(self.API_USERNAME, self.API_TOKEN))
        if req.status_code == 200:
            contributors = [user["login"] for user in req.json()]
            self.send_to_persistor(package_name, contributors_url, contributors)
            ch.basic_ack(delivery_tag = method.delivery_tag)
        elif req.status_code == 403:
            rate_limit_reset = int(req.headers["X-RateLimit-Reset"])
            reset_time = datetime.fromtimestamp(rate_limit_reset)
            print "Reached api cal limit. wiating until: %s"%reset_time.strftime("%Y-%m-%d %H:%M:%S")
            time_delta = reset_time - datetime.now()

            ch._connection.sleep(time_delta.total_seconds())
            # rejecting the message so it goes back to the queue and it could be processed after rate limit resets.
            ch.basic_reject(method.delivery_tag, requeue=True)
        else:
            print "error parsing package: %s - url: %s"%(package_name, contributors_url)
            ch.basic_ack(delivery_tag = method.delivery_tag)

    def send_to_persistor(self, package_name, contributors_url, contributors):
        connection = pika.BlockingConnection(pika.ConnectionParameters(
                host='localhost'))
        channel = connection.channel()

        message = {}
        message["package_name"] = package_name
        message["contributors_url"] = contributors_url
        message["contributors"] = contributors

        channel.queue_declare(queue='persistor_queue', durable=True)

        channel.basic_publish(exchange='',
                              routing_key='persistor_queue',
                              body=json.dumps(message),
                              properties=pika.BasicProperties(
                                 delivery_mode = 2, # make message persistent
                              ))
        connection.close()

    def get_repo_url(self, package_name):
        url = "https://packagist.org/packages/%s.json"%package_name

        req = requests.get(url)
        
        response_json = req.json()
        try:
            return response_json["package"]["repository"]
        except:
            return ""

    def start(self, api_username, api_token):
        self.API_USERNAME = api_username
        self.API_TOKEN = api_token
        connection = pika.BlockingConnection(pika.ConnectionParameters(
                host='localhost'))
        channel = connection.channel()

        channel.queue_declare(queue='parser_queue', durable=True)
        print ' [*] Waiting for messages. To exit press CTRL+C'
        
        channel.basic_qos(prefetch_count=1)
        channel.basic_consume(self.callback,
                              queue='parser_queue')

        channel.start_consuming()


def main(argv):
    api_username = ""
    api_token = ""
    try:
        opts, args = getopt.getopt(argv,"u:t:",["api_username=","api_token="])
    except getopt.GetoptError:
        api_username = ""
        api_token = ""
    for opt, arg in opts:
        if opt in ("-u", "--api_username"):
            api_username = arg
        elif opt in ("-t", "--api_token"):
            api_token = arg

    qw = Queue_Worker()
    qw.start(api_username, api_token)        
 
if __name__ == "__main__":
   main(sys.argv[1:])