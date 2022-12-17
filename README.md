# csc302-final

Description:

UpLift is a website where users can sign into their accounts to create and share recipes with one another.
The recipes created have a specific focus on helping an individual in their fitness journey. So recipes
can be anything from low calorie high protein meals to help people lose weight but keep muscle to meals
that have thousands of calories for those who are trying to put weight on. There really is no limit to
what a user can create. Users can upvote and downvote posts or comment on them if they like.

Link to project live on digdug: https://digdug.cs.endicott.edu/~clund/csc302fa22-lund-project/src/pages/signin.html

# File Hierarchy:
 - src
    - api-db
        - jwt.php
        - uplift-api.php
        - uplift.db
    - pages
        - header-navbar.html
        - hot.html
        - index.html
        - post.html
        - profile.html
        - signin.html
        - signup.html
    - styles
        - main.css
    - js
        - uplift.js

# API Actions
signup:
 - Method: POST
 - Params: string username, string password
 - Response: username, user_id

signin:
 - Method: POST
 - Params: string username, string password
 - Response: username, userURI, jwt

addRecipe:
 - Method: POST
 - Params: string title, string ingredients, string instructions
 - Response: recipe location

 # Data Model
 On the server side, functions will be created to store users and recipes into a database.
 Whereas, on the client side, the only information that should be stored is the jwt session
 information within localStorage.

# Features
 - Sign Up                          status: DONE
 - Sign in                          status: DONE
 - Sign out                         status: DONE
 - Add recipe                       status: NOT DONE
 - Edit recipe                      status: NOT DONE
 - Delete recipe                    status: NOT DONE
 - Add comments                     status: NOT DONE
 - Edit comments                    status: NOT DONE
 - Delete Comments                  status: NOT DONE
 - Upvote/Downvote Recipes          status: NOT DONE
 - Upvote/Downvote Comments         status: NOT DONE