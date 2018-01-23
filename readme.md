# research-coder-api

Backend supporting <a href="https://researchcoder.com">researchcoder.com</a>.
  
## Installation

#### Docker (the easy way)

You'll need:
+ <a href="https://git-scm.com/book/en/v2/Getting-Started-Installing-Git">git</a>
+ <a href="https://docs.docker.com/engine/installation/">docker</a>
+ <a href="https://docs.docker.com/compose/install/">docker-compose</a>

Clone the repo and enter the new directory
~~~
git clone https://github.com/uab-energetics/research-coder-api
cd research-coder-api
~~~

Start the development server, opting to run the database migrations. If you didn't add your user to the docker group, you may need to prefix with `sudo`:
~~~
./docker-dev.sh --migrate
~~~


