import os
import sys
import commands

sys.path.append('/var/www')

os.environ['PYTHON_EGG_CACHE'] = '/var/www/.python-egg'

def application(environ, start_response):
  status = '200 OK'

  response = commands.getoutput("df -h")
  response_headers = [('Content-type', 'text/plain'),
  ('Content-Length', str(len(response)))]
  start_response(status, response_headers)

  return [response]
