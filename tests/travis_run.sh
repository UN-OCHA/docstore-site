# Add files
(echo -n '{"filename":"test.pdf","mime":"application/pdf","data": "'; base64 ./files/test_xyzzy.pdf; echo '"}') | curl -X POST -H  "accept: application/json" -H  "API-KEY: abcd" -H "Content-Type: application/json" -d @-  http://127.0.0.1:8080/api/files

./silk -test.v -silk.url http://127.0.0.1:8080/api silk_create.md;
# SKIP ./silk -test.v -silk.url http://127.0.0.1:8080/api silk_exceptions.md;
