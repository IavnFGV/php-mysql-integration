import urllib
import uuid

import DataExtractor as de

import pymongo
import requests
from pymongo.errors import DuplicateKeyError

host = "localhost"
port = 27017

user_name = "root"
pass_word = "123"

myclient = pymongo.MongoClient(f'mongodb://{user_name}:{urllib.parse.quote_plus(pass_word)}@{host}:{port}/')

mydb = myclient["naukroom"]
# mycol = mydb["deals"]
# mycol.drop();
mycol = mydb["deals"]

# tempdeal =mycol.find_one({"id":1});

# tempdeal["_id"] = 999789;

# try:
#     mycol.insert_one(tempdeal)
# except DuplicateKeyError:
#     pass
# end_id =102818-1

def pushDataToCollection(deal):
    deal["_id"]=deal["id"]
    print(mycol.count_documents({}))
    try:
        mycol.insert_one(deal)
        print("ADDED")
    except DuplicateKeyError:
        print(f"ERROR:{deal}")
    return True
    # print(f'inserted {deal["id"]}')


de.iterateOverDeals(pushDataToCollection)