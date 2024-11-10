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
for deal in mycol.find({}):
    del deal["_id"]
    envelope ={"data":deal}
    meta = {"action":"history_load","entity_id":deal["id"],"correlation_id":str(uuid.uuid4()),"id":str(uuid.uuid4())}
    envelope["meta"]=meta
    URL = "http://localhost:80/integration.php"
    PARAMS = {'XDEBUG_SESSION_START':"IDEA_DEBUG"}
    r = requests.post(url = URL,params=PARAMS, json = envelope)




