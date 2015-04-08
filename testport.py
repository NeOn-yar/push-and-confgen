import os
import sys
import commands
import socket

from cgi import parse_qs, escape

sys.path.append('/var/www')

os.environ['PYTHON_EGG_CACHE'] = '/var/www/.python-egg'

def isOpen(ip,port):
  s = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
  try:
    s.connect((ip, int(port)))
    s.shutdown(2)
    return True
  except:
    return False

def application(environ, start_response):
  status = '200 OK'

  d = parse_qs(environ['QUERY_STRING'])
  ip = str(d.get('ip')[0])
  if isOpen(ip,'554'):
    output = 'true'
  else:
    output = 'false'
  
  #response = commands.getoutput("ping -c 10 " + ip)
  response_headers = [('Content-type', 'text/plain'),
  ('Content-Length', str(len(output)))]
  start_response(status, response_headers)

  return [output]
