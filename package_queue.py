import pika
import sys
import requests

connection = pika.BlockingConnection(pika.ConnectionParameters(
        host='localhost'))
channel = connection.channel()

channel.queue_declare(queue='parser_queue', durable=True)

req = requests.get('https://packagist.org/packages/list.json')
data = req.json()
packages = data["packageNames"]

for package in packages:
    channel.basic_publish(exchange='',
                          routing_key='parser_queue',
                          body=package,
                          properties=pika.BasicProperties(
                             delivery_mode = 2, # make message persistent
                          ))
    print(" [x] Sent %r" % package)
connection.close()