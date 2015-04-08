import os
import sys
import commands

from cgi import parse_qs, escape

sys.path.append('/var/www')

os.environ['PYTHON_EGG_CACHE'] = '/var/www/.python-egg'

def application(environ, start_response):
  status = '200 OK'
  
  d = parse_qs(environ['QUERY_STRING'])
  ip = str(d.get('ip')[0])
  print d.get('ip')
  
  response = commands.getoutput("ping -c 10 " + ip)
  response_headers = [('Content-type', 'text/plain'),
  ('Content-Length', str(len(response)))]
  start_response(status, response_headers)

  return [response]
