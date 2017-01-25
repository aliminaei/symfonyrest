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
    counter = 0

    def callback(self, ch, method, properties, body):
        self.counter = self.counter + 1
        if self.counter == 100:
            print "Reached api cal limit. wiating until!!!"
            sleep(10)
            # rejecting the message so it goes back to the queue and it could be processed after rate limit resets.
            ch.basic_reject(method.delivery_tag, requeue=True)
        else:
            print body
            ch.basic_ack(delivery_tag = method.delivery_tag)

    def start(self, api_username, api_token):
        self.API_USERNAME = api_username
        self.API_TOKEN = api_token
        connection = pika.BlockingConnection(pika.ConnectionParameters(
                heartbeat_interval=5,
                socket_timeout=2,
                host='localhost'))
        channel = connection.channel()

        channel.queue_declare(queue='task_queue', durable=True)
        print ' [*] Waiting for messages. To exit press CTRL+C'
        
        channel.basic_qos(prefetch_count=1)
        channel.basic_consume(self.callback,
                              queue='task_queue')

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