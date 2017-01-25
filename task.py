import pika
import sys
import requests

connection = pika.BlockingConnection(pika.ConnectionParameters(
        host='localhost'))
channel = connection.channel()

channel.queue_declare(queue='task_queue', durable=True)

for i in range(1, 1001):
    package = "message_%i"%i
    channel.basic_publish(exchange='',
                          routing_key='task_queue',
                          body=package,
                          properties=pika.BasicProperties(
                             delivery_mode = 2, # make message persistent
                          ))
    print(" [x] Sent %r" % package)
connection.close()