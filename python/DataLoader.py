import pymongo
import urllib
import uuid

from pipenv.cli.command import prog_name

import  DataExtractor as de
import requests




host = "localhost"
port = 27017

user_name = "root"
pass_word = "123"

myclient = pymongo.MongoClient(f'mongodb://{user_name}:{urllib.parse.quote_plus(pass_word)}@{host}:{port}/')

mydb = myclient["naukroom"]
# mycol = mydb["deals"]
# mycol.drop();
mycol = mydb["deals"]


end_id =102818-1

def pushDataToCollection(deal):
    deal["_id"]=deal["id"]
    print(mycol.count_documents({}))
    mycol.insert_one(deal)
    if(deal["id"] == end_id):
        return False
    return True
    # print(f'inserted {deal["id"]}')


# de.iterateOverDeals(pushDataToCollection)

# for deal in mycol.find({"label":{"$ne":None}},{'label':1}):
#     print(deal)

#
def remapCustomFields(deal):
    tempDict={}
    for key,value in deal["custom_fields"].items():
        if value != None:
            tempDict[key] = {"value":value}
        else:
            tempDict[key] = None
    deal["custom_fields"] = tempDict


# for deal in mycol.find({"custom_fields.d88705a61d5f8a109cab4994db7105734f6b4234":{"$ne":None}}):
        # mycol.find({"id":885})):
        # mycol.find({"custom_fields.d88705a61d5f8a109cab4994db7105734f6b4234":{"$ne:None"}})):
        # mycol.find({"id":885}):
        # mycol.find({}):
for deal in mycol.find({}):
    del deal["_id"]
    remapCustomFields(deal)
    envelope ={"data":deal}
    meta = {"action":"history_load","entity_id":deal["id"],"correlation_id":deal['correlation_id'],"id":str(uuid.uuid4())}
    del deal['correlation_id']
    envelope["meta"]=meta
    # URL = "https://mufiksoft.com/naukroom2/integration.php"
    # URL = "http://localhost:80/integration.php"
    # PARAMS = {'XDEBUG_SESSION_START':"IDEA_DEBUG"}
    print(envelope)
    # r = requests.post(url = URL,params=PARAMS, json = envelope)
    r = requests.post(url = URL, json = envelope)

# mycol.update({"id":1},{"$set":{"correlation_id":str(uuid.uuid4())}})

# mycol = mycol.create_index({"id":1})
# for i in range(19000,110705):
#     mycol.update_one({"id":i},{"$set":{"correlation_id":str(uuid.uuid4())}})
#     if(i % 1000 == 0):
#         print(i)


