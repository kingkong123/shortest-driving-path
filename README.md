# Shortest driving path on CodeIgniter

## Installation (required Docker)
 - download the source code from this repo
 - navigate into the root directory of the source in the terminal
 - create a Google API Key with Google Maps Directions API and Google Maps Distance Matrix API enabled
 - create a ``` .env ``` file to store the Google API Key by following the example from ``` .env.example ```
 - run ``` docker-compose up -d ```

After the installation, the server should be up and running in this address
```
http://localhost:8000/
```

The API endpoint is located at
```
http://localhost:8000/api
```
with the following functions

### Submit start point and route locations
Method: ```POST```

URL path ```http://localhost:8000/api/route```

Input body:
```
[
	["37.733706", "-122.446889"],
	["37.641688", "-122.403079"],
	["37.491127", "-122.230324"]
]
```

Response body:
Success:
```json
{ "token": "TOKEN" }
```

Error:
```json
{ "error": "ERROR_DESCRIPTION" }
```

### Get shortest driving route
Method: ```GET```

URL path ```http://localhost:8000/api/route/<TOKEN>```

Response body:
Success:
```json
{
    "status": "success",
    "path": [
        [
            "37.733706",
            "-122.446889"
        ],
        [
            "37.641688",
            "-122.403079"
        ],
        [
            "37.491127",
            "-122.230324"
        ]
    ],
    "total_distance": "54330",
    "total_time": "2589"
}
```

Error:
```json
{
	"status": "failure",
	"error": "ERROR_DESCRIPTION"
}
```
